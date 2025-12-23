<?php
/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (file_exists(__DIR__.'/configuration.local.php')) {
    require __DIR__ . '/configuration.local.php';
} else {
    require __DIR__ . '/configuration.php';
}

$requiredConstants = ['KIMAI_API_URL', 'KIMAI_API_TOKEN', 'DATABASE_CONNECTION', 'DATABASE_USER', 'DATABASE_PASSWORD', 'DATABASE_COLUMN', 'DATABASE_DATETIME_FORMAT'];
foreach ($requiredConstants as $constant) {
    if (!defined($constant)) {
        throw new Exception('Missing constant: '.  $constant);
    }
}

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;

(new SingleCommandApplication())
    ->setName('Sync Kimai data via API')
    ->setVersion('1.0')
    ->addOption('timesheets', null, InputOption::VALUE_NONE, 'Only sync timesheets (for hourly cronjob)')
    ->addOption('modified', null, InputOption::VALUE_REQUIRED, 'Only timesheets that were modified after this date will be synced, by default latest 24 hours. Format: 2022-01-14 13:45:47')
    ->setCode(function (InputInterface $input, OutputInterface $output) {

        $io = new SymfonyStyle($input, $output);

        $since = $input->getOption('modified');
        if ($since === null) {
            $since = new DateTimeImmutable('-24 hours');
        } else {
            try {
                $since = new DateTimeImmutable($since);
            } catch (Exception $ex) {
                $io->error('Invalid "since" date given, please check your format.');
                return 1;
            }
        }

        $dateConverter = function($fieldName, $date) {
            $converted = null;
            if ($date !== null) {
                $tmp = new DateTimeImmutable($date);
                $converted = $tmp->format(DATABASE_DATETIME_FORMAT);
            }
            return [$fieldName, $converted];
        };

        $connection = new PDO(DATABASE_CONNECTION, DATABASE_USER, DATABASE_PASSWORD);
        $clientOptions = [
            'base_uri' => KIMAI_API_URL,
            'verify' => false,
            'headers' => ['Authorization' => 'Bearer ' . KIMAI_API_TOKEN]
        ];

        if (defined('PROXY_URL') && !empty(PROXY_URL)) {
            $clientOptions['proxy'] = PROXY_URL;
        }

        $client = new Client($clientOptions);

        $doGet = function (Client $client, string $endpoint) {
            $response = $client->get($endpoint);

            if ($response->getStatusCode() === 404) {
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        };

        $syncEndpoint = function ($title, $settings) use ($io, $connection, $client, $doGet) {
            $apiEntities = [];
            $existingEntities = []; // mapping local id to kimai id in local database
            $localColumns = []; // column names on local side to prepare SQL statements

            // fetch the API result
            $page = 1;
            while (true) {
                $separator = (strpos($settings['endpoint'], '?') === false) ? '?' : '&';
                $url = sprintf('%s%spage=%s&size=500', $settings['endpoint'], $separator, $page);
                $results = $doGet($client, $url);

                if ($results === false) {
                    $io->error(sprintf('Failed to sync data for endpoint: %s', $settings['endpoint']));
                    break;
                }

                if (empty($results)) {
                    break;
                }

                // prepare the array of all entities for the local database by mapping columns
                foreach ($results as $entity) {
                    $newEntity = [];
                    foreach ($settings['mapping'] as $kimaiField => $localField) {
                        $key = $localField;
                        $value = $entity[$kimaiField];
                        // some values need to be converted to local format (eg. datetime)
                        if (is_callable($localField)) {
                            $tmp = call_user_func($localField, $entity, $kimaiField);
                            $key = $tmp[0];
                            $value = $tmp[1];
                        }
                        $newEntity[$key] = $value;
                    }
                    if (count($localColumns) === 0) {
                        $localColumns = array_keys($newEntity);
                    }
                    $apiEntities[$entity['id']] = $newEntity;
                }

                $page++;
                unset($results);
            }

            if (count($apiEntities) === 0) {
                $io->success('No data found to sync: ' . $title);
                return;
            }

            // a lambda function to escape the column name for MSSQL
            $formatColumnName = function(string $columnName) {
                return sprintf(DATABASE_COLUMN, $columnName);
            };

            $localColumns = array_map($formatColumnName, $localColumns);

            // some local variable names to make it easier
            $tableName = $settings['table'];

            // fetch all existing entries to decide if we update or insert
            $sql = sprintf('SELECT id, kimai_id FROM %s WHERE kimai_id IN (%s)', $tableName, implode(',', array_keys($apiEntities)));
            $stmt = $connection->prepare($sql);
            try {
                if ($stmt->execute() === false) {
                    $io->error($sql);
                }
            } catch (Exception $ex) {
                $io->error($sql . PHP_EOL . $ex->getMessage());
            }
            $existing = $stmt->fetchAll();
            foreach ($existing as $existingValues) {
                $existingEntities[$existingValues['kimai_id']] = $existingValues['id'];
            }

            // prepare the insert statement
            $columnsReplacer = [];
            for ($i = 0; $i < count($localColumns); $i++) {
                $columnsReplacer[] = '?';
            }
            $sqlInsert = sprintf('INSERT INTO %s (%s) VALUES (%s)', $tableName, implode(',', $localColumns), implode(',', $columnsReplacer));
            $stmtInsert = $connection->prepare($sqlInsert);

            // prepare the update statement
            $columnsReplacer = [];
            foreach ($localColumns as $localField) {
                $columnsReplacer[] = $localField . ' = ?';
            }
            $sqlUpdate = sprintf('UPDATE %s SET %s WHERE id = ?', $tableName, implode(',', $columnsReplacer));
            $stmtUpdate = $connection->prepare($sqlUpdate);

            foreach ($apiEntities as $kimaiId => $values) {
                if (array_key_exists($kimaiId, $existingEntities)) {
                    $values[] = $existingEntities[$kimaiId];
                    if ($stmtUpdate->execute(array_values($values)) === false) {
                        $io->error(sprintf('Failed updating "%s" for ID "%s" with: %s', $tableName, $existingEntities[$kimaiId], $stmtUpdate->errorInfo()[2]));
                    }
                } else {
                    if ($stmtInsert->execute(array_values($values)) === false) {
                        $io->error(sprintf('Failed inserting into "%s" with: %s', $tableName, $stmtInsert->errorInfo()[2]));
                    }
                }
            }

            $io->success('Synced ' . $title . ': ' . count($apiEntities));
        };

        $syncConfig = [
            'Customer' => [
                'table' => 'customer',
                'endpoint' => 'customers',
                'mapping' => [
                    'id' => 'kimai_id',
                    'name' => 'name',
                    'number' => 'number',
                ],
            ],
            'Projects' => [
                'table' => 'project',
                'endpoint' => 'projects',
                'mapping' => [
                    'id' => 'kimai_id',
                    'customer' => 'customer',
                    'name' => 'name',
                    'start' => function ($entity, $fieldName) use ($dateConverter) {
                        return $dateConverter('start', $entity[$fieldName]);
                    },
                    'end' => function ($entity, $fieldName) use ($dateConverter) {
                        return $dateConverter('end', $entity[$fieldName]);
                    },
                ],
            ],
            'Activities' => [
                'table' => 'activity',
                'endpoint' => 'activities',
                'mapping' => [
                    'id' => 'kimai_id',
                    'project' => 'project',
                    'name' => 'name',
                ],
            ],
            'Users' => [
                'table' => 'user',
                'endpoint' => 'users',
                'mapping' => [
                    'id' => 'kimai_id',
                    'alias' => 'alias',
                    'username' => 'username',
                ],
            ],
            'Teams' => [
                'table' => 'team',
                'endpoint' => 'teams',
                'mapping' => [
                    'id' => 'kimai_id',
                    'name' => 'name',
                ],
            ],
        ];

        $onlyTimesheets = $input->getOption('timesheets');
        if ($onlyTimesheets) {
            $syncConfig = [];
        }

        $syncConfig['Timesheets'] = [
            'table' => 'timesheet',
            'endpoint' => 'timesheets?user=all&modified_after=' . $since->format('Y-m-d\TH:i:s'),
            'mapping' => [
                'id' => 'kimai_id',
                'activity' => 'activity',
                'project' => 'project',
                'user' => 'user',
                'begin' => function ($entity, $fieldName) use ($dateConverter) {
                    return $dateConverter('begin', $entity[$fieldName]);
                },
                'end' => function ($entity, $fieldName) use ($dateConverter) {
                    return $dateConverter('end', $entity[$fieldName]);
                },
                'duration' => 'duration',
                'description' => function ($entity, $fieldName) {
                    $value = $entity[$fieldName];
                    if ($value !== null && mb_strlen($value) > 200) {
                        $value = mb_substr($value, 0, 200);
                    }
                    return ['description', $value];
                },
                'rate' => 'rate',
                'internalRate' => 'internalRate',
                'billable' => function ($entity, $fieldName) {
                    $value = 1;
                    if (!$entity[$fieldName]) {
                        $value = 0;
                    }
                    return ['billable', $value];
                },
            ],
        ];

        foreach ($syncConfig as $title => $settings)
        {
            $syncEndpoint($title, $settings);
        }

        if ($onlyTimesheets) {
            return 0;
        }

        // SPECIAL HANDLING FOR TEAMS
        $stmt = $connection->prepare('SELECT id, kimai_id FROM team');
        $stmt->execute();
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $teamProjects = [];
        $teamUsers = [];
        $deleteIds = [];

        $io->writeln('Syncing teams, user and project links ...');

        $progress = new ProgressBar($output, count($teams));

        foreach ($teams as $team) {
            $kimaiTeamId = $team['kimai_id'];
            $teamId = $team['id'];

            try {
                $team = $doGet($client, 'teams/' . $kimaiTeamId);
            } catch (ClientException $ex) {
                if ($ex->getResponse()->getStatusCode() === 404) {
                    $deleteIds[] = $teamId;
                    continue;
                }
            }

            foreach ($team['members'] as $member) {
                $teamUsers[$kimaiTeamId][] = $member['user']['id'];
            }
            foreach ($team['projects'] as $project) {
                $teamProjects[$kimaiTeamId][] = $project['id'];
            }

            usleep(500); // be polite and do not overstress remote Server/API
            $progress->advance();
        }
        $progress->finish();

        if (count($deleteIds) > 0) {
            foreach ($deleteIds as $deleteId) {
                // make sure table is always empty before inserting the relations between user and team
                $stmt = $connection->prepare('DELETE FROM team WHERE id = ' . $deleteId);
                $stmt->execute();
            }
        }

        // make sure table is always empty before inserting the relations between user and team
        $stmt = $connection->prepare('TRUNCATE team_user');
        $stmt->execute();

        $stmt = $connection->prepare('INSERT INTO team_user (team_kimai_id, user_kimai_id) VALUES (?, ?)');
        foreach ($teamUsers as $kimaiTeamId => $kimaiUserIds) {
            foreach ($kimaiUserIds as $kimaiUserId) {
                if ($stmt->execute([$kimaiTeamId, $kimaiUserId]) === false) {
                    $io->error(sprintf('Failed inserting into "team_user" with: %s', $stmt->errorInfo()[2]));
                }
            }
        }

        // make sure table is always empty before inserting the relations between project and team
        $stmt = $connection->prepare('TRUNCATE team_project');
        $stmt->execute();

        $stmt = $connection->prepare('INSERT INTO team_project (team_kimai_id, project_kimai_id) VALUES (?, ?)');
        foreach ($teamProjects as $kimaiTeamId => $kimaiProjectIds) {
            foreach ($kimaiProjectIds as $kimaiProjectId) {
                if ($stmt->execute([$kimaiTeamId, $kimaiProjectId]) === false) {
                    $io->error(sprintf('Failed inserting into "team_project" with: %s', $stmt->errorInfo()[2]));
                }
            }
        }

        return 0;
    })
    ->run();
