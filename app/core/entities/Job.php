<?php

namespace app\core\entities;

class Job extends Entity
{


    private $id;
    private $job_data;
    private $status;
    private $created_at;
    private $updated_at;

    /**
     * @return mixed
     */
    public function getId()
    {
        return (int)$this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return JobData
     */
    public function getJobData()
    {
        $temp = json_decode($this->job_data, true);
        if (is_string($temp))
            $temp = json_decode($temp, true);
        // Records inserted via prepared statements keep the addslashes() escaping from JobData::toArray()
        if (!empty($temp[JobData::VAR_CLASSNAME]) && is_string($temp[JobData::VAR_CLASSNAME]))
            $temp[JobData::VAR_CLASSNAME] = str_replace('\\\\', '\\', $temp[JobData::VAR_CLASSNAME]);
        return new JobData($temp);
    }

    /**
     * @param mixed $job_data
     */
    public function setJobData($job_data)
    {
        // A row from cron_jobs already contains JSON
        $this->job_data = is_string($job_data) ? $job_data : json_encode($job_data);
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * @param mixed $created_at
     */
    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    /**
     * @param mixed $updated_at
     */
    public function setUpdatedAt($updated_at)
    {
        $this->updated_at = $updated_at;
    }



    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'job_data' => $this->getJobData()->toArray(),
            'status' => $this->getStatus(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}