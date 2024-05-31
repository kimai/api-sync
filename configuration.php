<?php

const KIMAI_API_URL = 'https://demo.kimai.org/api/';
const KIMAI_API_TOKEN = 'api_kitten_super';
const DATABASE_CONNECTION = 'mysql:dbname=sync-test;host=127.0.0.1';
const DATABASE_USER = 'root';
const DATABASE_PASSWORD = 'password';
const DATABASE_COLUMN = '`%s`';
const DATABASE_DATETIME_FORMAT = 'Y-m-d H:i:s';

// you can set a proxy if you are in a corporate network
// const PROXY_URL = 'http://user:password@proxy.locale.dev:1234';

// -------------------------------------------------------------------------------------------
// using SQLServer for Power BI ? You might need adjusted settings:
//
// const DATABASE_CONNECTION = 'sqlsrv:server=sqlserv01;Database=sync-test';    // connection string in correct format
// const DATABASE_COLUMN = '[%s]';                                              // format of column names in UPDATE statements
