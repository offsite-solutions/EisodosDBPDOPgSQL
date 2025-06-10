# EisodosDBPDOPgSQL
Eisodos FW PDO:PostgreSQL Database Connector

## Prerequisites
- PHP 8.x
  - Tested with PHP 8.3
- Installed ext-pdo, ext-pdo_pgsql
- Eisodos framework
  - Minimum version 1.0.16

## Installation
Installation via composer:
```
composer install "offsite-solutions/eisodos-db-connector-pdo-pgsql"
```

## Configuration

```
[Database]
driver=pgsql
user=eisodos
password=eisodos
host=host.docker.internal
port=15432
# database name
dbname=eisodos
# named notation := | =>
namednotation=:=
# Connect timeout in seconds
connectTimeout=10
# disable, allow, prefer, require
sslmode=disable
options=--application_name=PDO_PgSQL_test
autoCommit=false
connectSQL=set client_encoding to 'UTF-8';set timezone to 'Europe/Budapest';
```

### driver
Available values are **pgsql, uri:///file/to/dsn**

### DBName 
Database name

### host 
Host name or IP

### port 
Port number (usually 5432)

### username
Username

### password
Password

### SSLMode
Available values are **require, disable, allow, prefer, require, verify-ca, verify-full**

### SSLCert
Client certificate filename, example: **[path]/client.crt**

### SSLKey
Client private key, example: **[path]/client.key**

### SSLRootCert
Root certificate, example: **[path]/ca.crt**

### connectTimeout
Connection timeout in millisec, example: **20000**

### prefetchSize
Prefetch size for queries, example: **20**

### persistent
Peristent connection enabled, available values are **true, false(default)**

### namedNotation
Named notation operator used for prepared SQLs, available values are **:= , =>**

### case
Force case of database objects' name. Available values are **natural(default), lower, upper**

### stringifyFetches
Stringify fetches, values are **true, false(default)**

### autoCommit
Set auto commit, values are **true, false(default)**

### Options
List of available options: https://www.postgresql.org/docs/current/libpq-connect.html#libpq-connstring

Example: **--application_name=PDO_PgSQL_test**

### ConnectSQL
Series of SQLs which will be executed right after successful connection.

## Initialization
```
  use Eisodos\Connectors\ConnectorPDOPgSQL;
  use Eisodos\Eisodos;
  
  Eisodos::$dbConnectors->registerDBConnector(new ConnectorPDOPgSQL(), 0);
  Eisodos::$dbConnectors->db()->connect();
  
  Eisodos::$dbConnectors->db()->disconnect();
```

## Methods
See Eisodos DBConnector Interface documentation: https://github.com/offsite-solutions/Eisodos

## Examples
### Get all rows of a query
```
Eisodos::$dbConnectors->db()->query(RT_ALL_ROWS, "select * from eisodos_test1 order by id desc", $back);
print_r($back);
print("   Number of rows returned: " . Eisodos::$dbConnectors->db()->getLastQueryTotalRows() . "\n");
```

### Execute DML
```
Eisodos::$dbConnectors->db()->executeDML("delete from eisodos_test1 where id>=1000");
Eisodos::$dbConnectors->db()->commit();
```

### Execute prepared DML
```
function generateCLOB($count_): string {
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $randomString = '';
  
  for ($i = 0; $i < $count_; $i++) {
    $index = random_int(0, strlen($characters) - 1);
    $randomString .= $characters[$index];
  }
  
  return $randomString;
}
  
$boundVariables = [];
Eisodos::$dbConnectors->db()->bind($boundVariables, 'ID', 'integer', 100);
Eisodos::$dbConnectors->db()->bind($boundVariables, 'C_INT', 'integer', 123);
Eisodos::$dbConnectors->db()->bind($boundVariables, 'C_FLOAT', 'float', 123.45);
Eisodos::$dbConnectors->db()->bind($boundVariables, 'C_DATE', 'date', date("Y-m-d"));
Eisodos::$dbConnectors->db()->bind($boundVariables, 'C_DATETIME', 'datetime', date("Y-m-d H:i:s"));
Eisodos::$dbConnectors->db()->bind($boundVariables, 'C_CLOB', 'clob', generateCLOB('80000'));
Eisodos::$dbConnectors->db()->bind($boundVariables, 'C_VARCHAR', 'string', 'test1');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'C_CHAR', 'char', 'Y');
print("Executing prepared DML\n");
Eisodos::$dbConnectors->db()->executePreparedDML2("INSERT INTO EISODOS_TEST1 (ID,C_INT,C_FLOAT,C_DATE,C_DATETIME,C_VARCHAR,C_CHAR,C_CLOB) \n" .
   "VALUES (:ID,:C_INT,:C_FLOAT,:C_DATE,:C_DATETIME,:C_VARCHAR,:C_CHAR,:C_CLOB)", $boundVariables);
Eisodos::$dbConnectors->db()->commit();
```

### Execute Stored Procedure with IN, IN_OUT, OUT parameters
```
$boundVariables = [];
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_ID', 'integer', '', 'IN_OUT');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_C_INT', 'integer', 456);
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_C_FLOAT', 'float', 789.45);
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_C_DATE', 'date', date("Y-m-d"));
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_C_DATETIME', 'datetime', date("Y-m-d H:i:s"));
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_C_CLOB', 'clob', generateCLOB(2000));
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_C_VARCHAR', 'string', 'test1_sp');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_C_CHAR', 'char', 'Y');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_CALLBACK', 'string', 'callback','IN_OUT');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_OUT_INT', 'int', '','OUT');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_OUT_VARCHAR2', 'string', '','OUT');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_OUT_DATE', 'date', '','OUT');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_OUT_DATETIME', 'datetime', '','OUT');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_OUT_CLOB', 'clob', '','OUT');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_ERROR_MSG', 'string', '','OUT');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_ERROR_CODE', 'integer', '','OUT');
Eisodos::$dbConnectors->db()->bind($boundVariables, 'P_LOGS', 'string', '[]','IN_OUT');
print("Executing stored procedure DML\n");
$resultArray = [];
Eisodos::$dbConnectors->db()->executeStoredProcedure('test_sp', $boundVariables, $resultArray, false);
Eisodos::$dbConnectors->db()->commit();
print("Result array\n");
print_r($resultArray);
```

#### Stored procedure for testing
```
CREATE TABLE EISODOS_TEST1 (
    ID         BIGINT NOT NULL,
    C_INT      INT4,
    C_FLOAT    FLOAT,
    C_DATE     DATE,
    C_DATETIME TIMESTAMP,
    C_CLOB     VARCHAR,
    C_VARCHAR  VARCHAR(255),
    C_CHAR     CHAR(1)
);

CREATE SEQUENCE eisodos_s;

CREATE OR REPLACE FUNCTION test_sp(
    INOUT p_id BIGINT,
    IN p_c_int INT4,
    IN p_c_float FLOAT,
    IN p_c_date DATE,
    IN p_c_datetime TIMESTAMP,
    IN p_c_clob VARCHAR,
    IN p_c_varchar VARCHAR,
    IN p_c_char CHAR,
    INOUT p_callback VARCHAR,
    OUT p_out_int INT4,
    OUT p_out_varchar2 VARCHAR,
    OUT p_out_date DATE,
    OUT p_out_datetime TIMESTAMP,
    OUT p_out_clob VARCHAR,
    OUT p_error_msg VARCHAR,
    OUT p_error_code INT4,
    INOUT p_logs JSONB DEFAULT NULL
)
    CALLED ON NULL INPUT
    VOLATILE
    LANGUAGE plpgsql
AS
$$
BEGIN

    p_error_code := 0;
    p_error_msg := NULL;
    p_logs := NULL;

    IF (p_id IS NULL)
    THEN
        SELECT NEXTVAL('eisodos_s') INTO p_id;
    END IF;

    INSERT INTO EISODOS_TEST1 (ID, C_INT, C_FLOAT, C_DATE, C_DATETIME, C_CLOB, C_VARCHAR, C_CHAR)
    VALUES (p_id,
            p_c_int,
            p_c_float,
            p_c_date,
            p_c_datetime,
            p_c_clob,
            p_c_varchar,
            p_c_char);

    p_callback := 'out '||p_callback;
    p_out_int := p_c_int+1;
    p_out_varchar2 := 'out '||p_c_varchar;
    p_out_date := p_c_date+1;
    p_out_datetime := p_c_datetime+interval '1 day';
    p_out_clob := 'out '||p_c_clob;

    IF p_error_code<>0
    THEN
        RAISE EXCEPTION '[%] %', p_error_code, p_error_msg;
    END IF;

END
$$;
```