<?php
namespace Dsc\Cron;

/**
 * @author Benjamin Laugueux <benjamin@yzalis.com>
 */
class BaseJob
{
    /**
     * @var $regex
     */
    static $_regex = array(
        'minute'     => '/[\*,\/\-0-9]+/',
        'hour'       => '/[\*,\/\-0-9]+/',
        'dayOfMonth' => '/[\*,\/\-\?LW0-9A-Za-z]+/',
        'month'      => '/[\*,\/\-0-9A-Z]+/',
        'dayOfWeek'  => '/[\*,\/\-0-9A-Z]+/',
        'command'    => '/^(.)*$/',
    );

    /**
     * @var string
     */
    protected $minute = "0";

    /**
     * @var string
     */
    protected $hour = "*";

    /**
     * @var string
     */
    protected $dayOfMonth = "*";

    /**
     * @var string
     */
    protected $month = "*";

    /**
     * @var string
     */
    protected $dayOfWeek = "*";

    /**
     * @var string
     */
    protected $command = null;

    /**
     * @var string
     */
    protected $comments = null;

    /**
     * @var string
     */
    protected $logFile = null;

    /**
     * @var string
     */
    protected $logSize = null;

    /**
     * @var string
     */
    protected $errorFile = null;

    /**
     * @var string
     */
    protected $errorSize = null;

    /**
     * @var DateTime
     */
    protected $lastRunTime = null;

    /**
     * @var string
     */
    protected $status = 'unknown';
    
    /**
     * @var boolean
     */
    protected $active = false;

    /**
     * @var $hash
     */
    protected $hash = null;

    /**
     * Return the minute
     *
     * @return string
     */
    public function getMinute()
    {
        return $this->minute;
    }

    /**
     * Return the hour
     *
     * @return string
     */
    public function getHour()
    {
        return $this->hour;
    }

    /**
     * Return the day of month
     *
     * @return string
     */
    public function getDayOfMonth()
    {
        return $this->dayOfMonth;
    }

    /**
     * Return the month
     *
     * @return string
     */
    public function getMonth()
    {
        return $this->month;
    }

    /**
     * Return the day of week
     *
     * @return string
     */
    public function getDayOfWeek()
    {
        return $this->dayOfWeek;
    }

    /**
     * Return the command
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Return the status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * Return the active state
     *
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Return the comments
     *
     * @return string
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * Return error file
     *
     * @return string
     */
    public function getErrorFile()
    {
        return $this->errorFile;
    }

    /**
     * Return the error file size
     *
     * @return string
     */
    public function getErrorSize()
    {
        return $this->errorSize;
    }

    /**
     * Return log file
     *
     * @return string
     */
    public function getLogFile()
    {
        return $this->logFile;
    }

    /**
     * Return the log file size
     *
     * @return string
     */
    public function getLogSize()
    {
        return $this->logSize;
    }

    /**
     * Set the status
     *
     * @param string
     *
     * @return Job
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }
    
    /**
     * Set the active state
     *
     * @param mixed
     *
     * @return Job
     */
    public function setActive($active=true)
    {
        $this->active = empty($active) ? false : true;
    
        return $this;
    }    

    /**
     * Set the log file size
     *
     * @param string
     *
     * @return Job
     */
    public function setLogSize($logSize)
    {
        $this->logSize = $logSize;

        return $this;
    }

    /**
     * Set the error file size
     *
     * @param string
     *
     * @return Job
     */
    public function setErrorSize($errorSize)
    {
        $this->errorSize = $errorSize;

        return $this;
    }
}