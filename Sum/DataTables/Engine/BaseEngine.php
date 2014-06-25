<?php

namespace Sum\DataTables\Engine;


abstract class BaseEngine
{
    private static $_remote = FALSE;
    private static $_request = array();
    protected $columns = array();
    protected $limit;
    protected $offset;
    protected $order = array();
    protected $bound = array();
    protected $search = array();
    protected $output = [
        "draw"            => 0,
        "recordsTotal"    => 0,
        "recordsFiltered" => 0,
        "data"            => [],
    ];
    private $_searchFormat = '%search%';
    private $_bindFormat = ':bind';


    public static function setRequest($request)
    {
        self::$_request = $request;
    }

    protected function request($index, $default = NULL)
    {
        if (empty(self::$_request))
            self::$_request = $_REQUEST;

        if (empty(self::$_request[$index]))
            return $default;

        return self::$_request[$index];
    }

    protected function handleFiltering()
    {
        $search  = $this->request('search');
        $columns = $this->request('columns');
        $tcol    = array_keys($this->columns);
        //global generate or like
        if (is_array($search) AND $search['value'] != '') {
            $this->bound = ["search" => $this->searchFormat($search['value'])];
            for ($i = 0, $ien = count($columns); $i < $ien; $i ++) {
                $name   = $columns[$i]['name'];
                $colKey = $name ? $this->columns[$name] : $tcol[$i];
                if ($columns[$i]['searchable'] == 'true') {
                    $this->search['global'][] = $this->columns[$colKey] . ' LIKE ' . $this->bindFormat("search");
                }
            }
        }

        //individual generate and like
        for ($i = 0, $ien = count($columns); $i < $ien; $i ++) {
            $name   = $columns[$i]['name'];
            $colKey = $name ? $this->columns[$name] : $tcol[$i];
            $str    = $columns[$i]['search']['value'];
            if ($columns[$i]['searchable'] == 'true' && $str != '') {
                $this->bound              = ["search_$i" => $this->searchFormat($str)];
                $this->search['column'][] = $this->columns[$colKey] . ' LIKE ' . $this->bindFormat("search_$i");
            }
        }

        return $this;
    }

    protected function handleOrdering()
    {
        if ($order = $this->request('order')) {
            $columns = $this->request('columns');
            $tcol    = array_keys($this->columns);

            for ($i = 0, $ien = count($order); $i < $ien; $i ++) {
                $name   = $columns[$i]['name'];
                $colKey = $name ? $this->columns[$name] : $tcol[$i];
                if ($columns[$i]['orderable'] == 'true') {
                    $column               = $this->columns[$colKey];
                    $this->order[$column] = strtoupper($order[$i]['dir']);
                }
            }

            return $this;
        }

        return FALSE;
    }

    protected function handlePaging()
    {
        $this->output['draw'] = $this->request('draw', 0);

        if (isset(self::$_request['start']) AND $this->request('length') != - 1) {
            $this->offset = (string) $this->request('start');
            $this->limit  = $this->request('length');

            return $this;
        }

        return FALSE;
    }

    private function searchFormat($value = '')
    {
        return preg_replace('/\w+/i', $value, $this->_searchFormat);
    }

    public function setSearchFormat($format = '%search%')
    {
        $this->_searchFormat = $format;

        return $this;
    }

    private function bindFormat($value)
    {
        return preg_replace('/\w+/i', $value, $this->_bindFormat);
    }

    public function setBindFormat($format = ':bind:')
    {
        $this->_bindFormat = $format;

        return $this;
    }

    public static function remoteAllowed()
    {
        return self::$_remote;
    }

    public static function allowRemote()
    {
        self::$_remote = TRUE;
    }

    public function getColumns()
    {
        $columns = [];
        foreach ($this->columns as $i => $col) {
            if (is_numeric($i))
                $columns[] = $col;
            else
                $columns[] = $i;
        }

        return $columns;
    }

    public function getRealColumns()
    {
        return $this->columns;
    }
}