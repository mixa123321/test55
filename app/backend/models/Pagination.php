<?php

namespace app\backend\models;

class Pagination
{
    public const LIMIT_PER_PAGE_MIN = 5;
    public const LIMIT_PER_PAGE = 10;
    public const LIMIT_PER_PAGE_BIG = 20;
    public const MAX_COUNT_SKUS_FOR_SHOW = 1000;
    private $current_page;
    private $page_size;
    private $items = [];
    private $total_count = 0;

    public function __construct($page = 1, $page_size = self::LIMIT_PER_PAGE)
    {
        $this->current_page = (int)$page > 0 ? (int)$page : 1;
        $this->page_size = (int)$page_size > 0 ? (int)$page_size : self::LIMIT_PER_PAGE;
    }

    public function getPageCount()
    {
        return ceil($this->getTotalCount() / $this->page_size);
    }

    public function getCurrentPage()
    {
        return $this->current_page;
    }

    public function getPerPage()
    {
        return $this->page_size;
    }

    public function getOffset()
    {
        return ($this->current_page - 1) * $this->page_size;
    }

    public function setItems(array $items)
    {
        $this->items = $items;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function setTotalCount($total_count)
    {
        $this->total_count = (int)$total_count;
    }

    public function getTotalCount()
    {
        return $this->total_count;
    }

    public function getLimitStatement()
    {
        return "{$this->getPerPage()} OFFSET {$this->getOffset()}";
    }

    public function getSlicedItems()
    {
        return array_slice($this->items, $this->getOffset(), $this->getPerPage());
    }
}