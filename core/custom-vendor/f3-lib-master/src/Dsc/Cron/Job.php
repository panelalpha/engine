<?php
namespace Dsc\Cron;

/**
 * Represent a cron job
 *
 * @author Benjamin Laugueux <benjamin@yzalis.com>
 */
class Job extends BaseJob
{
    /**
     * To string
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (\Exception $e) {
            return '# '.$e;
        }
    }
    
    /**
     * Returns an associative array of object's public properties
     * removing any that begin with a double-underscore (__)
     *
     * @param boolean $public
     *            If true, returns only the public properties.
     *            
     * @return array
     */
    public function cast( $public = true )
    {
        $vars = get_object_vars( $this );
        if ($public)
        {
            foreach ( $vars as $key => $value )
            {
                if (substr( $key, 0, 2 ) == '__')
                {
                    unset( $vars[$key] );
                }
            }
        }
        return $vars;
    }

    /**
     * Parse crontab line into Job object
     *
     * @param $jobLine
     *
     * @return Job
     * @throws \InvalidArgumentException
     */
    static function parse($jobLine)
    {
        // split the line
        $parts = explode(' ', $jobLine);
        
        // is the line commented out (inactive)?
        $active = true;
        if ($parts[0] == '#') {
            $active = false;
            unset($parts[0]);
            $parts = array_values($parts);
        }
        
        // does the line use a special time spec?
        switch($parts[0]) 
        {
            case "@reboot":
                unset($parts[0]);
                array_unshift($parts, "@reboot", "*", "*", "*", "*");
                break;            
            case "@yearly":
            case "@annually":
                unset($parts[0]);
                array_unshift($parts, "0", "0", "1", "1", "*");
                break;            
            case "@monthly":
                unset($parts[0]);
                array_unshift($parts, "0", "0", "1", "*", "*");
                break;            
            case "@weekly":
                unset($parts[0]);
                array_unshift($parts, "0", "0", "*", "*", "0");
                break;            
            case "@daily":
            case "@midnight":
                unset($parts[0]);
                array_unshift($parts, "0", "0", "*", "*", "*");
                break;            
            case "@hourly":
                unset($parts[0]);
                array_unshift($parts, "0", "*", "*", "*", "*");
                break;            
        }

        // check the number of part
        if (count($parts) < 5) {
            throw new \InvalidArgumentException('Wrong job number of arguments.');
        }

        // analyse command
        $command = implode(' ', array_slice($parts, 5));

        // prepare variables
        $lastRunTime = $logFile = $logSize = $errorFile = $errorSize = $comments = null;

        // extract comment
        if (strpos($command, '#')) {
            list($command, $comment) = explode('#', $command);
            $comments = trim($comment);
        }

        // extract error file
        if (strpos($command, '2>>')) {
            list($command, $errorFile) = explode('2>>', $command);
            $errorFile = trim($errorFile);
        }

        // extract log file
        if (strpos($command, '>>')) {
            list($command, $logFile) = explode('>>', $command);
            $logFile = trim($logFile);
        }

        // compute last run time, and file size
        if (isset($logFile) && file_exists($logFile)) {
            $lastRunTime = filemtime($logFile);
            $logSize = filesize($logFile);
        }
        if (isset($errorFile) && file_exists($errorFile)) {
            $lastRunTime = max($lastRunTime ? : 0, filemtime($errorFile));
            $errorSize = filesize($errorFile);
        }

        $command = trim($command);

        // compute status
        $status = 'error';
        if ($logSize === null && $errorSize === null) {
            $status = 'unknown';
        } else if ($errorSize === null || $errorSize == 0) {
            $status =  'success';
        }

        // set the Job object
        $job = new Job();
        
        // Handle $parts[0] == @reboot
        if ($parts[0] == '@reboot') 
        {
            $job
            ->setReboot()
            ->setCommand($command)
            ->setErrorFile($errorFile)
            ->setErrorSize($errorSize)
            ->setLogFile($logFile)
            ->setLogSize($logSize)
            ->setComments($comments)
            ->setLastRunTime($lastRunTime)
            ->setStatus($status)
            ->setActive($active)
            ;
        }
        else 
        {
            $job
            ->setMinute($parts[0])
            ->setHour($parts[1])
            ->setDayOfMonth($parts[2])
            ->setMonth($parts[3])
            ->setDayOfWeek($parts[4])
            ->setCommand($command)
            ->setErrorFile($errorFile)
            ->setErrorSize($errorSize)
            ->setLogFile($logFile)
            ->setLogSize($logSize)
            ->setComments($comments)
            ->setLastRunTime($lastRunTime)
            ->setStatus($status)
            ->setActive($active)
            ;
        }

        return $job;
    }

    /**
     * Generate a unique hash related to the job entries
     *
     * @return Job
     */
    private function generateHash()
    {
        $this->hash = hash('md5', serialize(array(
            $this->getMinute(),
            $this->getHour(),
            $this->getDayOfMonth(),
            $this->getMonth(),
            $this->getDayOfWeek(),
            $this->getCommand(),

        )));

        return $this;
    }

    /**
     * Get an array of job entries
     *
     * @return array
     */
    public function getEntries()
    {
        return array(
            $this->getMinute(),
            $this->getHour(),
            $this->getDayOfMonth(),
            $this->getMonth(),
            $this->getDayOfWeek(),
            $this->getCommand(),
            $this->prepareLog(),
            $this->prepareError(),
            $this->prepareComments(),
        );
    }

    /**
     * Render the job for crontab
     *
     * @return string
     */
    public function render()
    {
        if (null === $this->getCommand()) {
            throw new \InvalidArgumentException('You must specify a command to run.');
        }

        // Create / Recreate a line in the crontab
        $line = trim(implode(" ", $this->getEntries()));

        if (!$this->getActive()) {
            $line = '# ' . $line;
        }
        
        return $line;
    }

    /**
     * Prepare comments
     *
     * @return string or null
     */
    public function prepareComments()
    {
        if (null !== $this->getComments()) {
            return '# ' . $this->getComments();
        } else {
            return null;
        }
    }

    /**
     * Prepare log
     *
     * @return string or null
     */
    public function prepareLog()
    {
        if (null !== $this->getLogFile()) {
            return '>> ' . $this->getLogFile();
        } else {
            return null;
        }
    }

    /**
     * Prepare log
     *
     * @return string or null
     */
    public function prepareError()
    {
        if (null !== $this->getErrorFile()) {
            return '2>> ' . $this->getErrorFile();
        } else if ($this->prepareLog()) {
            return '2>&1';
        } else {
            return null;
        }
    }
    /**
     * Return the error file content
     *
     * @return string
     */
    public function getErrorContent()
    {
        if ($this->getErrorFile() && file_exists($this->getErrorFile())) {
            return file_get_contents($this->getErrorFile());
        } else {
            return null;
        }
    }

    /**
     * Return the log file content
     *
     * @return string
     */
    public function getLogContent()
    {
        if ($this->getLogFile() && file_exists($this->getLogFile())) {
            return file_get_contents($this->getLogFile());
        } else {
            return null;
        }
    }

    /**
     * Return the last job run time
     *
     * @return DateTime|null
     */
    public function getLastRunTime()
    {
        return $this->lastRunTime;
    }

    /**
     * Return the job unique hash
     *
     * @return Job
     */
    public function getHash()
    {
        if (null === $this->hash) {
            $this->generateHash();
        }

        return $this->hash;
    }
    
    /**
     * Set this job's schedule using one of the special cron shortcut commands
     *  
     * @param unknown $shortcut
     * @return \Dsc\Cron\Job
     */
    public function setSimple( $shortcut )
    {
        $parts = array();
        
        switch ($shortcut)
        {
            case "@reboot":
                $this->setReboot();
                break;
            case "@yearly":
            case "@annually":
                array_unshift($parts, "0", "0", "1", "1", "*");
                break;
            case "@monthly":
                array_unshift($parts, "0", "0", "1", "*", "*");
                break;
            case "@weekly":
                array_unshift($parts, "0", "0", "*", "*", "0");
                break;
            case "@daily":
            case "@midnight":
                array_unshift($parts, "0", "0", "*", "*", "*");
                break;
            case "@hourly":
                array_unshift($parts, "0", "*", "*", "*", "*");
                break;            
        }

        if (!empty($parts)) 
        {
            $this
            ->setMinute($parts[0])
            ->setHour($parts[1])
            ->setDayOfMonth($parts[2])
            ->setMonth($parts[3])
            ->setDayOfWeek($parts[4]);            
        }
        
        return $this;
    }    
    
    /**
     * Set this Job to use the special @reboot command
     * 
     * @return Job
     */
    public function setReboot()
    {
        $this->minute = '@reboot';
        $this->hour = null;
        $this->dayOfMonth = null;
        $this->month = null;
        $this->dayOfWeek = null;
        
        return $this->generateHash();
    }

    /**
     * Set the minute (* 1 1-10,11-20,30-59 1-59 *\/1)
     *
     * @param string
     *
     * @return Job
     */
    public function setMinute($minute)
    {
        if (!preg_match(self::$_regex['minute'], $minute)) {
            throw new \InvalidArgumentException(sprintf('Minute "%s" is incorect', $minute));
        }

        $this->minute = $minute;

        return $this->generateHash();
    }

    /**
     * Set the hour
     *
     * @param string
     *
     * @return Job
     */
    public function setHour($hour)
    {
        if (!preg_match(self::$_regex['hour'], $hour)) {
            throw new \InvalidArgumentException(sprintf('Hour "%s" is incorect', $hour));
        }

        $this->hour = $hour;

        return $this->generateHash();
    }

    /**
     * Set the day of month
     *
     * @param string
     *
     * @return Job
     */
    public function setDayOfMonth($dayOfMonth)
    {
        if (!preg_match(self::$_regex['dayOfMonth'], $dayOfMonth)) {
            throw new \InvalidArgumentException(sprintf('DayOfMonth "%s" is incorect', $dayOfMonth));
        }

        $this->dayOfMonth = $dayOfMonth;

        return $this->generateHash();
    }

    /**
     * Set the month
     *
     * @param string
     *
     * @return Job
     */
    public function setMonth($month)
    {
        if (!preg_match(self::$_regex['month'], $month)) {
            throw new \InvalidArgumentException(sprintf('Month "%s" is incorect', $month));
        }

        $this->month = $month;

        return $this->generateHash();
    }

    /**
     * Set the day of week
     *
     * @param string
     *
     * @return Job
     */
    public function setDayOfWeek($dayOfWeek)
    {
        if (!preg_match(self::$_regex['dayOfWeek'], $dayOfWeek)) {
            throw new \InvalidArgumentException(sprintf('DayOfWeek "%s" is incorect', $dayOfWeek));
        }

        $this->dayOfWeek = $dayOfWeek;

        return $this->generateHash();
    }

    /**
     * Set the command
     *
     * @param string
     *
     * @return Job
     */
    public function setCommand($command)
    {
        if (!preg_match(self::$_regex['command'], $command)) {
            throw new \InvalidArgumentException(sprintf('Command "%s" is incorect', $command));
        }

        $this->command = $command;

        return $this->generateHash();
    }

    /**
     * Set the last job run time
     *
     * @param int
     *
     * @return Job
     */
    public function setLastRunTime($lastRunTime)
    {
        $this->lastRunTime = \DateTime::createFromFormat('U', $lastRunTime);

        return $this;
    }

    /**
     * Set the comments
     *
     * @param string
     *
     * @return Job
     */
    public function setComments($comments)
    {
        if (is_array($comments)) {
            $comments = implode($comments, ' ');
        }

        $this->comments = $comments;

        return $this;
    }

    /**
     * Set the log file
     *
     * @param string
     *
     * @return Job
     */
    public function setLogFile($logFile)
    {
        $this->logFile = $logFile;

        return $this->generateHash();
    }

    /**
     * Set the error file
     *
     * @param string
     *
     * @return Job
     */
    public function setErrorFile($errorFile)
    {
        $this->errorFile = $errorFile;

        return $this->generateHash();
    }
}