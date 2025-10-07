<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos\Connectors;
  
  use Eisodos\Eisodos;
  use Eisodos\Interfaces\DBConnectorInterface;
  use PDO;
  use PDOException;
  use RuntimeException;
  
  /**
   * Eisodos PDO::PgSQL Connector class
   *
   * Config values:
   * [Database]
   * driver=pgsql|uri:///file/to/dsn
   * username=
   * password=
   * DBName=
   * SSLMode=require,disable,allow,prefer,require,verify-ca,verify-full
   * SSLCert=[path]/client.crt
   * SSLKey=[path]/client.key
   * SSLRootCert=[path]/ca.crt;
   * connectTimeout=
   * prefetchSize=
   * case=natural(default)|lower|upper
   * stringifyFetches=true|false(default)
   * autoCommit=true|false(default)
   * persistent=true|false(default)
   * options= list of available options: https://www.postgresql.org/docs/current/libpq-connect.html#libpq-connstring
   * connectSQL=list of query run after connection separated by ;
   */
  class ConnectorPDOPgSQL implements DBConnectorInterface {
  
    /** @var string DB Syntax */
    private string $_dbSyntax='pgsql';
    
    /** @var PDO null */
    private PDO $connection;
    
    /** @var array */
    private array $lastQueryColumnNames = [];
    
    /** @var int */
    private int $lastQueryTotalRows = 0;
    
    /** @var string */
    private string $named_notation_separator = '=>';
    
    public function connected(): bool {
      return (isset($this->connection));
    }
    
    public function __destruct() {
      $this->disconnect();
    }
    
    /**
     * https://www.php.net/manual/en/pdo.connect.php
     *
     * @inheritDoc
     * throws RuntimeException
     * @throws \Exception
     */
    public function connect($databaseConfigSection_ = 'Database', $connectParameters_ = [], $persistent_ = false): void {
      if (!isset($this->connection)) {
        $databaseConfig = array_change_key_case(Eisodos::$configLoader->importConfigSection($databaseConfigSection_, '', false));
        
        $connectString = Eisodos::$utils->safe_array_value($databaseConfig, 'driver', 'pgsql') .
          (!str_contains(Eisodos::$utils->safe_array_value($databaseConfig, 'driver', 'pgsql'), ':') ? ':' : '');
        $connectParameters = [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false
        ];
        $username = '';
        $password = '';
        
        /*
         * PDO::ATTR_PREFETCH - Setting the prefetch size allows you to balance speed against memory usage for your application.
         *                      Not all database/driver combinations support setting of the prefetch size.
         *                      A larger prefetch size results in increased performance at the cost of higher memory usage.
         * PDO::ATTR_TIMEOUT - Sets the timeout value in seconds for communications with the database.
         * PDO::ATTR_CASE - Force column names to a specific case specified by the PDO::CASE_* constants.
         *                  Values: PDO::CASE_NATURAL, PDO::CASE_LOWER, PDO::CASE_UPPER
         * PDO::ATTR_ORACLE_NULLS - Convert empty strings to SQL NULL values on data fetches.
         *                          Values: PDO::NULL_NATURAL, PDO::NULL_EMPTY_STRING, PDO::NULL_TO_STRING
         * PDO::ATTR_PERSISTENT - Request a persistent connection, rather than creating a new connection.
         *                        See Connections and Connection management for more information on this attribute.
         * PDO::ATTR_STRINGIFY_FETCHES - Forces all fetched values (except null) to be treated as strings.
         *                               null values remain unchanged unless PDO::ATTR_ORACLE_NULLS is set to PDO::NULL_TO_STRING.
         * PDO::ATTR_MAX_COLUMN_LEN - Sets the maximum column name length.
         * PDO::ATTR_AUTOCOMMIT - If this value is false, PDO attempts to disable autocommit so that the connection begins a transaction.
         */
        
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'user') !== '') {
          $username = Eisodos::$utils->safe_array_value($databaseConfig, 'user');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'password') !== '') {
          $password = Eisodos::$utils->safe_array_value($databaseConfig, 'password');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'host') !== '') {
          $connectString .= ';host=' . Eisodos::$utils->safe_array_value($databaseConfig, 'host');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'port') !== '') {
          $connectString .= ';port=' . Eisodos::$utils->safe_array_value($databaseConfig, 'port');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'dbname') !== '') {
          $connectString .= ';dbname=' . Eisodos::$utils->safe_array_value($databaseConfig, 'dbname');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'namednotation') !== '') {
          $this->named_notation_separator = Eisodos::$utils->safe_array_value($databaseConfig, 'namednotation');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'sslmode') !== '') {
          $connectString .= ';sslmode=' . Eisodos::$utils->safe_array_value($databaseConfig, 'sslmode');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'sslcert') !== '') {
          $connectString .= ';sslcert=' . Eisodos::$utils->safe_array_value($databaseConfig, 'sslcert');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'sslkey') !== '') {
          $connectString .= ';sslkey=' . Eisodos::$utils->safe_array_value($databaseConfig, 'sslkey');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'sslrootcert') !== '') {
          $connectString .= ';sslrootcert=' . Eisodos::$utils->safe_array_value($databaseConfig, 'sslrootcert');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'options') !== '') {
          $connectString .= ";options='" . Eisodos::$utils->safe_array_value($databaseConfig, 'options') . "'";
        }
        
        // options
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'connectTimeout') !== '') {
          $connectParameters[PDO::ATTR_TIMEOUT] = (int)Eisodos::$utils->safe_array_value($databaseConfig, 'connectTimeout');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'prefetchSize') !== '') {
          $connectParameters[PDO::ATTR_PREFETCH] = (int)Eisodos::$utils->safe_array_value($databaseConfig, 'prefetchSize');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'case') !== '') {
          switch (Eisodos::$utils->safe_array_value($databaseConfig, 'case')) {
            case 'natural':
              $connectParameters[PDO::ATTR_CASE] = PDO::CASE_NATURAL;
              break;
            case 'lower':
              $connectParameters[PDO::ATTR_CASE] = PDO::CASE_LOWER;
              break;
            case 'upper':
              $connectParameters[PDO::ATTR_CASE] = PDO::CASE_UPPER;
              break;
          }
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'stringifyFetches') !== '') {
          $connectParameters[PDO::ATTR_STRINGIFY_FETCHES] = (Eisodos::$utils->safe_array_value($databaseConfig, 'stringifyFetches') === 'true');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'persistent') !== '') {
          $connectParameters[PDO::ATTR_PERSISTENT] = (Eisodos::$utils->safe_array_value($databaseConfig, 'persistent') === 'true');
        }
        if (Eisodos::$utils->safe_array_value($databaseConfig, 'autoCommit') !== '') {
          $connectParameters[PDO::ATTR_AUTOCOMMIT] = (Eisodos::$utils->safe_array_value($databaseConfig, 'autoCommit') === 'true');
        }
        
        try {
          $this->connection = new PDO($connectString, $username, $password, $connectParameters);
        } catch (PDOException $e) {
          Eisodos::$parameterHandler->setParam('DBError', $e->getCode() . ' - ' . $e->getMessage());
          throw new RuntimeException('Database Open Error!');
        }
        
        Eisodos::$logger->trace('Database connected - ' . $connectString);
        
        $connectSQL = Eisodos::$utils->safe_array_value($databaseConfig, 'connectsql');
        
        if (stripos($connectSQL, 'set datestyle') === false) {
          $connectSQL = "set datestyle to ISO;" . $connectSQL;
        }
        
        foreach (explode(';', $connectSQL) as $sql) {
          if ($sql !== '') {
            $this->query(RT_FIRST_ROW_FIRST_COLUMN, $sql);
          }
        }
        
      }
    }
    
    private function _getColumnNames($resultSet): void {
      for ($i = 0; $i < $resultSet->columnCount(); $i++) {
        $this->lastQueryColumnNames[] = $resultSet->getColumnMeta($i)['name'];
      }
    }
    
    /** @inheritDoc
     * https://phpdelusions.net/pdo/fetch_modes
     * */
    public function query(
      int $resultTransformation_, string $SQL_, &$queryResult_ = NULL, $getOptions_ = [], $exceptionMessage_ = ''
    ): mixed {
      
      $this->lastQueryColumnNames = [];
      $this->lastQueryTotalRows = 0;
      $queryResult_ = NULL;
      
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      try {
        $resultSet = $this->connection->prepare($SQL_);
      } catch (PDOException $e) {
        if (!$exceptionMessage_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        $resultSet['error'] = $e->getMessage();
        
        return false;
      }
      
      $resultSet->execute([]);
      $this->_getColumnNames($resultSet);
      
      if ($resultTransformation_ === RT_RAW) {
        $rows = $resultSet->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
          $resultSet->closeCursor();
          $this->lastQueryTotalRows = 0;
          
          return false;
        }
        
        $queryResult_ = $rows;
        $resultSet->closeCursor();
        $this->lastQueryTotalRows = count($queryResult_);
        
        return true;
      }
      
      if ($resultTransformation_ === RT_FIRST_ROW) {
        $row = $resultSet->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
          $resultSet->closeCursor();
          $this->lastQueryTotalRows = 0;
          
          return false;
        }
        
        $queryResult_ = $row;
        $resultSet->closeCursor();
        $this->lastQueryTotalRows = 1;
        
        return true;
      }
      
      if ($resultTransformation_ === RT_FIRST_ROW_FIRST_COLUMN) {
        $row = $resultSet->fetch(PDO::FETCH_NUM);
        if (!$row || count($row) === 0) {
          $resultSet->closeCursor();
          $this->lastQueryTotalRows = 0;
          
          return '';
        }
        
        $resultSet->closeCursor();
        $this->lastQueryTotalRows = 1;
        
        return $row[0];
      }
      
      if ($resultTransformation_ === RT_ALL_KEY_VALUE_PAIRS
        || $resultTransformation_ === RT_ALL_FIRST_COLUMN_VALUES
        || $resultTransformation_ === RT_ALL_ROWS
        || $resultTransformation_ === RT_ALL_ROWS_ASSOC) {
        
        $queryResult_ = [];
        
        // TODO okosabban, gyorsabban
        if ($resultTransformation_ === RT_ALL_KEY_VALUE_PAIRS) {
          while (($row = $resultSet->fetch(PDO::FETCH_NUM))) {
            $queryResult_[$row[0]] = $row[1];
          }
        } else if ($resultTransformation_ === RT_ALL_FIRST_COLUMN_VALUES) {
          while (($row = $resultSet->fetch(PDO::FETCH_NUM))) {
            $queryResult_[] = $row[0];
          }
        } else if ($resultTransformation_ === RT_ALL_ROWS) {
          $queryResult_ = $resultSet->fetchAll(PDO::FETCH_ASSOC);
        } else if ($resultTransformation_ === RT_ALL_ROWS_ASSOC) {
          $indexFieldName = Eisodos::$utils->safe_array_value($getOptions_, 'indexFieldName', false);
          if (!$indexFieldName) {
            throw new RuntimeException("Index field name is mandatory on RT_ALL_ROWS_ASSOC result type");
          }
          while (($row = $resultSet->fetch(PDO::FETCH_ASSOC))) {
            $queryResult_[$row[$indexFieldName]] = $row;
          }
        }
        
        $resultSet->closeCursor();
        $this->lastQueryTotalRows = count($queryResult_);
        
        return true;
      }
      
      throw new RuntimeException("Unknown query result type");
      
    }
    
    /** @inheritDoc */
    public function getLastQueryColumns(): array {
      return $this->lastQueryColumnNames;
    }
    
    /** @inheritDoc */
    public function getLastQueryTotalRows(): int {
      return $this->lastQueryTotalRows;
    }
    
    /** @inheritDoc */
    public function disconnect($force_ = false): void {
      /* free up the object to close connection */
    }
    
    /** @inheritDoc */
    public function startTransaction(string|null $savePoint_ = NULL): void {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      $this->connection->beginTransaction();
    }
    
    /** @inheritDoc */
    public function commit(): void {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      if ($this->connection->inTransaction()) {
        $this->connection->commit();
      }
    }
    
    /** @inheritDoc */
    public function rollback(string|null $savePoint_ = NULL): void {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      $this->connection->rollback();
    }
    
    /** @inheritDoc */
    public function inTransaction(): bool {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      $inTransaction = $this->connection->inTransaction();
      
      return ($inTransaction ?? false);
    }
    
    public function executeDML(string $SQL_, $throwException_ = true): int {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      try {
        $resultSet = $this->connection->prepare($SQL_);
      } catch (PDOException $e) {
        if (!$throwException_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        return false;
      }
      
      $resultSet->execute([]);
      $numRows = $resultSet->rowCount();
      $resultSet->closeCursor();
      
      return $numRows;
    }
    
    private function _convertType(string $dataType_, mixed &$value_): int {
      
      $dataType_ = strtolower($dataType_);
      
      if ($dataType_ === '' || $value_ == '') {
        return PDO::PARAM_NULL;
      }
      
      $type = match ($dataType_) {
        'bool' => PDO::PARAM_BOOL,
        'int', 'integer', 'bigint' => PDO::PARAM_INT,
        default => PDO::PARAM_STR,
      };
      
      switch ($dataType_) {
        case 'int':
        case 'bigint':
        case 'integer':
          $value_ = (int)$value_;
          break;
        case 'float':
          $value_ = (float)$value_;
          break;
      }
      
      return $type;
    }
    
    public function executePreparedDML(string $SQL_, $dataTypes_ = [], &$data_ = [], $throwException_ = true): int {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      if (count($dataTypes_) !== count($data_)) {
        $_POST["__EISODOS_extendedError"] = 'executePreparedDML missing data type or data';
        throw new RuntimeException('executePreparedDML missing data type or data');
      }
      
      try {
        $resultSet = $this->connection->prepare($SQL_);
      } catch (PDOException $e) {
        if (!$throwException_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        return false;
      }
      
      $countData = count($data_);
      for ($i = 0; $i < $countData; $i++) {
        $this->_convertType($dataTypes_[$i], $data_[$i]);
        $resultSet->bindParam($i + 1, $data_[$i], $dataTypes_[$i]);
      }
      
      $resultSet->execute();
      $numRows = $resultSet->rowCount();
      $resultSet->closeCursor();
      
      return $numRows;
    }
    
    /**
     * @inheritDoc
     */
    public function executePreparedDML2(string $SQL_, array $boundVariables_, $throwException_ = true): int|bool {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      try {
        $resultSet = $this->connection->prepare($SQL_);
      } catch (PDOException $e) {
        if (!$throwException_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        return false;
      }
      
      foreach ($boundVariables_ as $variableName => $parameters) {
        $type = $parameters['type'];
        $type = $this->_convertType($type, $parameters['value']);
        if ($parameters['mode_'] === 'INOUT' || $parameters['mode_'] === 'OUT') {
          $type |= PDO::PARAM_INPUT_OUTPUT;
        }
        $resultSet->bindParam($variableName, $parameters['value'], $type);
      }
      
      $resultSet->execute();
      $numRows = $resultSet->rowCount();
      $resultSet->closeCursor();
      
      return $numRows;
    }
    
    /** @inheritDoc */
    public function bind(array &$boundVariables_, string $variableName_, string $dataType_, string $value_, $inOut_ = 'IN'): void {
      $boundVariables_[$variableName_] = array();
      if ($dataType_ === "clob" && $value_ === '') // Empty CLOB bug / invalid LOB locator specified, force type to text
      {
        $boundVariables_[$variableName_]["type"] = "text";
      } else {
        $boundVariables_[$variableName_]["type"] = $dataType_;
      }
      $boundVariables_[$variableName_]["value"] = $value_;
      $boundVariables_[$variableName_]["mode_"] = $inOut_;
    }
    
    /** @inheritDoc */
    public function bindParam(array &$boundVariables_, string $parameterName_, string $dataType_): void {
      $this->bind($boundVariables_, $parameterName_, $dataType_, Eisodos::$parameterHandler->getParam($parameterName_));
    }
    
    /** @inheritDoc */
    public function executeStoredProcedure(string $procedureName_, array $inputVariables_, array &$resultVariables_, $throwException_ = true, $case_ = CASE_UPPER): bool {
      if (!isset($this->connection)) {
        throw new RuntimeException("Database not connected");
      }
      
      $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
      
      $sql = "";
      
      foreach ($inputVariables_ as $parameterName => $parameterProperties) {
        if ($parameterProperties["mode_"] !== "OUT") {
          $sql .= ($sql ? "," : "") . $parameterName . " " . $this->named_notation_separator . " :" . $parameterName;
        }
      }
      $sql = "select * from " . $procedureName_ . "(" . $sql . ")";
      
      try {
        $resultSet = $this->connection->prepare($sql);
      } catch (PDOException $e) {
        if (!$throwException_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        return false;
      }
      
      foreach ($inputVariables_ as $paramName => $parameterProperties) {
        $resultVariables_[$paramName] = $parameterProperties["value"];
        $type = $parameterProperties['type'];
        $type = $this->_convertType($type, $parameterProperties['value']);
        
        if ($parameterProperties['mode_'] === 'INOUT' || $parameterProperties['mode_'] === 'IN_OUT') {
          $type |= PDO::PARAM_INPUT_OUTPUT;
        }
        
        /*
        Eisodos::$logger->trace('Binding: ' . $paramName . ' - ' .
            substr($resultVariables_[$paramName], 0, 30) . ' - ' .
            $type . ' - ' .
            (($parameterProperties["type"] === "integer" || $parameterProperties["type"] === "text") ? (32766 / 2) : -1));
        */
        
        // binding parameters except OUT ones in pgsql
        if ($parameterProperties["mode_"] !== "OUT") {
          $resultSet->bindParam($paramName,
            $resultVariables_[$paramName],
            $type
          );
        }
      }
      
      try {
        $resultSet->execute();
      } catch (PDOException $e) {
        if (!$throwException_) {
          $_POST["__EISODOS_extendedError"] = $e->getMessage();
          throw new RuntimeException($e->getMessage());
        }
        
        return false;
      }
      
      // getting back OUT parameters
      if ($driver === 'pgsql') {
        $result = $resultSet->fetch(PDO::FETCH_ASSOC);
        if (is_array($result)) {
          $resultVariables_ = array_merge($resultVariables_, array_change_key_case($result, $case_));
        }
      }
      
      return true;
    }
    
    /**
     * @inheritDoc
     */
    public function getConnection(): mixed {
      return $this->connection;
    }
    
    /**
     * @inheritDoc
     */
    public function emptySQLField($value_, $isString_ = true, $maxLength_ = 0, $exception_ = "", $withComma_ = false, $keyword_ = "NULL"): string {
      if ($value_ === '') {
        if ($withComma_) {
          return "NULL, ";
        }
        
        return "NULL";
      }
      if ($isString_) {
        if ($maxLength_ > 0 && mb_strlen($value_, 'UTF-8') > $maxLength_) {
          if ($exception_) {
            throw new RuntimeException($exception_);
          }
          
          $value_ = substr($value_, 0, $maxLength_);
        }
        $result = "'" . Eisodos::$utils->replace_all($value_, "'", "''") . "'";
      } else {
        $result = $value_;
      }
      if ($withComma_) {
        $result .= ", ";
      }
      
      return $result;
    }
    
    /**
     * @inheritDoc
     */
    public function nullStr($value_, $isString_ = true, $maxLength_ = 0, $exception_ = '', $withComma_ = false): string {
      return $this->emptySQLField($value_, $isString_, $maxLength_, $exception_, $withComma_);
    }
    
    /**
     * @inheritDoc
     */
    public function defaultStr($value_, $isString_ = true, $maxLength_ = 0, $exception_ = '', $withComma_ = false): string {
      return $this->emptySQLField($value_, $isString_, $maxLength_, $exception_, $withComma_, 'DEFAULT');
    }
    
    /**
     * @inheritDoc
     */
    public function nullStrParam(string $parameterName_, $isString_ = true, $maxLength_ = 0, $exception_ = '', $withComma_ = false): string {
      return $this->emptySQLField(Eisodos::$parameterHandler->getParam($parameterName_), $isString_, $maxLength_, $exception_, $withComma_);
    }
    
    /**
     * @inheritDoc
     */
    public function defaultStrParam(string $parameterName_, $isString_ = true, $maxLength_ = 0, $exception_ = '', $withComma_ = false): string {
      return $this->emptySQLField(Eisodos::$parameterHandler->getParam($parameterName_), $isString_, $maxLength_, $exception_, $withComma_, 'DEFAULT');
    }
    
    /**
     * @inheritDoc
     */
    public function DBSyntax(): string {
      return $this->_dbSyntax;
    }
    
    /**
     * @inheritDoc
     */
    public function toList(mixed $value_, bool $isString_ = true, int $maxLength_ = 0, string $exception_ = '', bool $withComma_ = false): string {
      $result = '';
      foreach (explode(',', $value_) as $value) {
        $result .= ($result === '' ? '' : ',') . $this->nullStr(trim($value), $isString_, $maxLength_, $exception_);
      }
      $result = '(' . $result . ')';
      if ($withComma_) {
        $result .= ", ";
      }
      
      return $result;
    }
    
  }