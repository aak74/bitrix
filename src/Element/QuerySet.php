<?php

namespace Akop\Element;

class QuerySet
{
    private $tableName = "";
    private $select = [];
    private $filter = [];
    private $order = [];
    private $limit;
    private $primaryKeyName;

    public function __construct($tableName, $primaryKeyName = 'id')
    {
        $this->tableName = $tableName;
        $this->primaryKeyName = $primaryKeyName;
    }

    public function getSelectSQL(array $fields = [])
    {
        // \Akop\Util::pre($fields, 'getSelectSQL');
        $sql = $this->buildSelect() . PHP_EOL;
        $sql .= $this->buildFrom() . PHP_EOL;
        $sql .= $this->buildFilter($fields) . PHP_EOL;
        $sql .= $this->buildOrder() . PHP_EOL;
        $sql .= $this->buildLimit() . PHP_EOL;
        // echo $sql;
        return preg_replace('/\n+/', PHP_EOL, $sql);
    }

    public function getAddSQL(array $params)
    {
        $fields = '';
        foreach ($params as $key => $value) {
            $fields .= "`$key`='$value',";
        }
        return "INSERT INTO `$this->tableName` SET "
            . $this->removeLastComma($fields);
    }

    public function getUpdateSQL($primaryKey, array $params)
    {
        $fields = '';
        foreach ($params as $key => $value) {
            $fields .= "`$key`='$value',";
        }

        return "UPDATE `$this->tableName` SET "
            . $this->removeLastComma($fields)
            . " WHERE `$this->primaryKeyName`=$primaryKey";
    }

    public function getDeleteSQL($primaryKey)
    {
        return "DELETE FROM `$this->tableName`"
            . " WHERE `$this->primaryKeyName`=$primaryKey";
    }

    public function addSelect(array $params = [])
    {
        $this->select = array_merge(
            $this->select,
            $params
        );
    }

    public function addFilter(array $params = [])
    {
        $this->filter = array_merge(
            $this->filter,
            $params
        );
    }

    public function addOrder(array $params = [])
    {
        $this->order = array_merge(
            $this->order,
            $params
        );
    }

    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    private function buildSelect()
    {
        if (empty($this->select)) {
            return "SELECT `$this->tableName`.*";
        }

        $select = '';
        foreach ($this->select as $field) {
            $select .= "`$this->tableName`.`$field`,";
        }
        return 'SELECT ' . $this->removeLastComma($select);
    }

    private function buildFrom()
    {
        return "FROM `$this->tableName`";
    }

    private function buildFilter(array $fields = [])
    {
        if (empty($this->filter)) {
            // \Akop\Util::pre([$fields, $this], 'buildFilter empty');
            return "";
        }

        $fieldPrefix = new FieldPrefix($fields);
        $fields = $fieldPrefix->getFieldsPrefixes(array_keys($this->filter));
        // \Akop\Util::pre($fields, 'buildFilter');

        $filter = '';
        foreach ($fields as $fieldName => $prefix) {
            $key = $prefix . $fieldName;

            $filter .= $this->getExpression(
                $fieldName,
                $this->filter[$key],
                $prefix
            );
        }
        return 'WHERE ' . $this->removeLastAnd($filter);
    }

    private function getExpression($fieldName, $fieldValue, $prefix = '')
    {
        $operand = $this->getOperand(
            $fieldValue,
            $prefix
        );
        switch ($operand) {
            case 'IN':
            case 'NOT IN':
                $values = '';
                foreach ($fieldValue as $value) {
                    $values .= "'$value',";
                }
                return "`$this->tableName`.`$fieldName` $operand ({$this->removeLastComma($values)}) AND ";
                break;
            case 'BETWEEN':
                return "`$this->tableName`.`$fieldName` BETWEEN '{$fieldValue[0]}' AND '{$fieldValue[1]}' AND ";
                break;
            default:
                return "`$this->tableName`.`$fieldName` $operand '$fieldValue' AND ";
                break;
        }
    }

    /**
     * Возвращает операнд в соответствии со значаениями параметров
     * @param $fieldName string имя поля
     * @param $fieldValue string значение поля
     * @todo Учитывать префиксы в соответствии с битриксовыми стандартами
     */
    private function getOperand($fieldValue, $prefix = '')
    {
        if (is_array($fieldValue)) {
          if ($prefix == '><') {
            return 'BETWEEN';
          }
          if ($prefix == '!=') {
              return 'NOT IN';
          }
          return 'IN';
        }
        // \Akop\Util::pre([$fieldValue, $prefix], 'getOperand');

        if (is_numeric($fieldValue) || \Akop\Util::isDateValid($fieldValue)) {
            if (empty($prefix)) {
                return '=';
            }

            if ($prefix === "!=") {
                return '<>';
            }

            return $prefix;
        }

        if ($prefix === "!=") {
            return 'NOT LIKE';
        }

        if (!empty($prefix)) {
            return $prefix;
        }

        return 'LIKE';
    }

    /**
    * Возвращает строку для ORDER
    */
    private function buildOrder()
    {
        if (empty($this->order)) {
            return "";
        }

        $order = '';
        foreach ($this->order as $fieldName => $direction) {
            $order .= $this->getOrderStr($fieldName, $direction);
        }
        return 'ORDER BY ' . $this->removeLastComma($order);
    }

    /**
     * Возвращает строку для ORDER для отдельного поля
     * с указанием направления сортировки
     */
    private function getOrderStr($fieldName, $direction)
    {
        if (is_numeric($fieldName)) {
            return "`$this->tableName`.`$direction`,";
        }
        return "`$this->tableName`.`$fieldName` $direction,";

    }

    /**
     * Удаляет последнюю запятую
     */
    private function removeLastComma($var)
    {
        return substr($var, 0, strlen($var) - 1);
    }

    /**
     * Удаляет последний AND
     */
    private function removeLastAnd($var)
    {
        return substr($var, 0, strlen($var) - 5);
    }

    /**
     * Возвращает строку для LIMIT
     */
    private function buildLimit()
    {
        if (empty($this->limit)) {
            return "";
        }
        if (is_array($this->limit)) {
            return "LIMIT {$this->limit[0]},{$this->limit[1]}";
        }
        return 'LIMIT ' . $this->limit;
    }

}
