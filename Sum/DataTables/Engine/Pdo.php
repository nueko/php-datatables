<?php
namespace Sum\DataTables\Engine;

class Pdo extends BaseEngine
{
    /**
     * @var \PDO
     */
    private $_connection;
    private $_table;
    private $_select;
    private $_groups;
    private $_having;
    private $join = array();
    private $_where;
    private $_query;
    private $_requestQuery;

    public function __construct($instance)
    {
        $this->_connection = $instance;
    }

    public function select($columns)
    {
        if (is_string($columns)) {
            if ('*' === trim($columns)) {
                //TODO
                throw new \Exception('SELECT * NOT Implemented');
            }
            $this->_select = $columns;
            $patern        = '/(.*)\s+as\s+(\w*)/i';
            foreach ($this->_explode(',', $columns) as $val) {
                $val    = trim($val);
                $column = trim(preg_replace($patern, '$2', $val));
                if ($val == $column) {
                    $this->columns[] = $val;
                } else {
                    $this->columns[$column] = trim(preg_replace($patern, '$1', $val));
                }
            }
        } else {
            $this->columns = $columns;
            foreach ($columns as $alias => $col) {
                $this->_select .= is_numeric($alias) ? $col : "$col AS $alias";
                ($col !== end($columns)) AND $this->_select .= ', ';

            }
            $this->_select = rtrim($this->_select, ',');
        }

        return $this;
    }

    public function from($table)
    {
        $this->_table = $table;

        return $this;
    }

    public function join($target, $condition = '', $type = '')
    {
        if (is_array($target)) {
            $alias  = key($target);
            $target = $target[$alias];
        }

        if (isset($alias)) {
            $this->join[$alias] = array(
                'target' => "($target)",
                'on'     => $condition,
                'type'   => $type
            );
        } else {
            $this->join[] = array(
                'target' => $target,
                'on'     => $condition,
                'type'   => $type
            );
        }

        return $this;
    }

    public function leftJoin($target, $condition = '')
    {
        $this->join($target, $condition, 'LEFT');

        return $this;
    }

    public function rightJoin($target, $condition = '')
    {
        $this->join($target, $condition, 'RIGHT');

        return $this;
    }

    public function where($condition)
    {
        if (empty($this->_where))
            $this->_where = $condition;
        else
            $this->_where .= " AND $condition";

        return $this;
    }

    public function groupBy($cols)
    {
        $this->_groups = is_string($cols) ? $cols : implode(', ', $cols);

        return $this;
    }

    public function having($con)
    {
        $this->_having = $con;

        return $this;
    }

    public function build()
    {
        if ($this->join)
            foreach ($this->join as $alias => $joined) {
                $this->_query .= " {$joined['type']} JOIN {$joined['target']}";
                if (! is_numeric($alias)) {
                    $this->_query .= " AS $alias";
                }
                if (isset($joined['on']))
                    $this->_query .= " ON {$joined['on']}";
            }
        empty($this->_where) OR $this->_query .= " WHERE ({$this->_where})";

        $this->_requestQuery = $this->_query;
        $glue = empty($this->_where) ? " WHERE " : " AND ";
        $this->handleFiltering();
        if (! empty($this->search['global']))
            $this->_requestQuery .= $glue . "(".implode(' OR ', $this->search['global']).")";
        if (! empty($this->search['column']))
            $this->_requestQuery .= $glue . implode(' AND ', $this->search['column']);

        empty($this->_groups) OR $this->_query .= " GROUP BY " . $this->_groups;
        empty($this->_having) OR $this->_query .= " HAVING " . $this->_having;

        if ($this->handleOrdering()) {
            $this->_requestQuery .= " ORDER BY ";
            foreach ($this->order as $col => $dir) {
                $this->_requestQuery .= "$col $dir";
                ($dir !== end($this->order)) AND $this->_requestQuery .= ', ';
            }
        }
        $this->handlePaging() AND $this->_requestQuery .= " LIMIT {$this->offset},{$this->limit}";

        return $this;
    }

    public function make($asArray = FALSE)
    {
        $this->build();

        $this->_connection->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_NUM);

        $data = $this->_connection->prepare(
            "SELECT SQL_CALC_FOUND_ROWS {$this->_select} FROM {$this->_table} {$this->_requestQuery}"
        );

        $resFilterLength = $this->_connection->prepare("SELECT FOUND_ROWS()");

        $resTotalLength = $this->_connection->prepare("SELECT COUNT(1) FROM {$this->_table} {$this->_query}");

        $resFilterLength->execute();
        $this->output['recordsFiltered'] = $resFilterLength->fetch()[0];
        $resTotalLength->execute($this->bound);
        $this->output['recordsTotal'] = $resTotalLength->fetch()[0];
        $data->execute($this->bound);
        $this->output['data'] = $data->fetchAll();
        if ($asArray) {
            return $this->output;
        } else {
            if (parent::remoteAllowed() AND $callback = $this->request('callback')) {
                return $callback.'('.json_encode($this->output).');';
            } else {
                return json_encode($this->output);
            }
        }
    }


    /**
     * Explode, but ignore delimiter until closing characters are found
     * extracted from ignited datatables (shame on me)
     *
     * @param string $delimiter
     * @param string $str
     * @param string $open
     * @param string $close
     * @return mixed $retval
     */
    private function _explode($delimiter, $str, $open = '(', $close = ')')
    {
        $retval  = array();
        $hold    = array();
        $balance = 0;
        $parts   = explode($delimiter, $str);

        foreach ($parts as $part) {
            $hold[] = $part;
            $balance += $this->_balanceChars($part, $open, $close);

            if ($balance < 1) {
                $retval[] = implode($delimiter, $hold);
                $hold     = array();
                $balance  = 0;
            }
        }

        if (count($hold) > 0)
            $retval[] = implode($delimiter, $hold);

        return $retval;
    }

    /**
     * Return the difference of open and close characters
     * extracted from ignited datatables (shame on me)
     *
     * @param string $str
     * @param string $open
     * @param string $close
     * @return int $retval
     */
    private function _balanceChars($str, $open, $close)
    {
        $openCount  = substr_count($str, $open);
        $closeCount = substr_count($str, $close);
        $retval     = $openCount - $closeCount;

        return $retval;
    }

} 