<?php

namespace NilPortugues\Tests\Foundation\Helpers;

use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;

class Clients implements Identity
{
    protected $id;
    protected $name;
    protected $date;
    protected $totalOrders;
    protected $totalEarnings;

    /**
     * Clients constructor.
     *
     * @param $id
     * @param $name
     * @param $date
     * @param $totalOrders
     * @param $totalEarnings
     */
    public function __construct($id, $name, $date, $totalOrders, $totalEarnings)
    {
        $this->id = $id;
        $this->name = $name;
        $this->date = $date;
        $this->totalOrders = $totalOrders;
        $this->totalEarnings = $totalEarnings;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Returns value for `name`.
     *
     * @return mixed
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Returns value for `date`.
     *
     * @return mixed
     */
    public function date()
    {
        return $this->date;
    }

    /**
     * Returns value for `totalOrders`.
     *
     * @return mixed
     */
    public function totalOrders()
    {
        return $this->totalOrders;
    }

    /**
     * Returns value for `totalEarnings`.
     *
     * @return mixed
     */
    public function totalEarnings()
    {
        return $this->totalEarnings;
    }

    /**
     * @return mixed
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->id;
    }
}
