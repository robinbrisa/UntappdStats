<?php

namespace App\Entity\Event;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="event_message")
 * @ORM\Entity(repositoryClass="App\Repository\Event\MessageRepository")
 */
class Message
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;
    
    /**
     * @ORM\Column(type="string")
     */
    private $message_line_1;
    
    /**
     * @ORM\Column(type="string")
     */
    private $message_line_2;
    
    /**
     * @ORM\Column(type="string")
     */
    private $message_line_3;
    
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $start_date;
    
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $end_date;
    
    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $last_time_displayed;
    
    /**
     * @ORM\ManyToOne(targetEntity="Event", inversedBy="messages", cascade={"remove"})
     * @ORM\JoinColumn(name="event_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $event;
    
    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", name="created_at", nullable=true)
     */
    private $created_at;
    
    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", name="updated_at", nullable=true)
     */
    private $updated_at;
    
    
    public function __toString()
    {
        $name = '[' . strip_tags($this->message_line_1) . "|" . strip_tags($this->message_line_2) . "|" . strip_tags($this->message_line_3) . ']';
        $timeLimits = "";
        if ($this->start_date) {
            $timeLimits = ' (Starting' . $this->start_date->format('d-m-y H:i') . ')';
        }
        if ($this->end_date) {
            $timeLimits = ' (Ending' . $this->end_date->format('d-m-y H:i') . ')';
        }
        if ($this->start_date && $this->end_date) {
            $timeLimits = ' (' . $this->start_date->format('d/m/y H:i') . ' - ' . $this->end_date->format('d/m/y H:i') . ')';
        }
        return $name . $timeLimits;
    }
    
    public function __construct() {
        $this->last_time_displayed = new \DateTime();
    }
    
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Set event
     *
     * @param \App\Entity\Event\Event $event
     *
     * @return Message
     */
    public function setEvent(\App\Entity\Event\Event $event = null)
    {
        $this->event = $event;
        
        return $this;
    }
    
    /**
     * Get event
     *
     * @return \App\Entity\Checkin\Checkin
     */
    public function getEvent()
    {
        return $this->event;
    }
    
    /**
     * Set startDate
     *
     * @param \DateTime $startDate
     
     * @return Message
     */
    public function setStartDate($startDate)
    {
        $this->start_date = $startDate;
        return $this;
    }
    
    /**
     * Get startDate
     *
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->start_date;
    }
    
    /**
     * Set endDate
     *
     * @param \DateTime $endDate
     
     * @return Message
     */
    public function setEndDate($endDate)
    {
        $this->end_date = $endDate;
        return $this;
    }
    
    /**
     * Get endDate
     *
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->end_date;
    }
    
    /**
     * Set lastTimeDisplayed
     *
     * @param \DateTime $lastTimeDisplayed
     
     * @return Message
     */
    public function setLastTimeDisplayed($lastTimeDisplayed)
    {
        $this->last_time_displayed = $lastTimeDisplayed;
        return $this;
    }
    
    /**
     * Get lastTimeDisplayed
     *
     * @return \DateTime
     */
    public function getLastTimeDisplayed()
    {
        return $this->last_time_displayed;
    }
    
    /**
     * Set messageLine1
     *
     * @param String $messageLine1
     
     * @return Message
     */
    public function setMessageLine1($messageLine1)
    {
        $this->message_line_1 = $messageLine1;
        return $this;
    }
    
    /**
     * Get messageLine1
     *
     * @return String
     */
    public function getMessageLine1()
    {
        return $this->message_line_1;
    }
    
    /**
     * Set messageLine2
     *
     * @param String $messageLine2
     
     * @return Message
     */
    public function setMessageLine2($messageLine2)
    {
        $this->message_line_2 = $messageLine2;
        return $this;
    }
    
    /**
     * Get messageLine2
     *
     * @return String
     */
    public function getMessageLine2()
    {
        return $this->message_line_2;
    }
    
    /**
     * Set messageLine3
     *
     * @param String $messageLine3
     
     * @return Message
     */
    public function setMessageLine3($messageLine3)
    {
        $this->message_line_3 = $messageLine3;
        return $this;
    }
    
    /**
     * Get messageLine3
     *
     * @return String
     */
    public function getMessageLine3()
    {
        return $this->message_line_3;
    }
    
    
    /**
     * Get createdAt
     *
     * @return \Datetime
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }
    
    /**
     * Get updatedAt
     *
     * @return \Datetime
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }
}