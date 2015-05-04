<?php
namespace IvixLabs\Coproc;


class CoprocDemux
{

    private $size = 1;

    private $maxMessages = 1;

    private $maxCycles = 1000;

    /**
     * @var string
     */
    private $cliCommand;

    /**
     * @var callable
     */
    private $messageProducer;

    /**
     * @var callable
     */
    private $resultCollector;

    public function start()
    {
        if ($this->cliCommand === null) {
            throw new \RuntimeException('CLI command is not specified');
        }
        $cliCommand = $this->cliCommand;

        if ($this->messageProducer === null) {
            throw new \RuntimeException('Message producer is not specified');
        }
        $messageProducer = $this->messageProducer;

        if ($this->resultCollector === null) {
            throw new \RuntimeException('Result collector is not specified');
        }
        $resultCollector = $this->resultCollector;


        $maxCoprocsCount = $this->size;
        $maxMessagesCount = $this->maxMessages;
        $maxCyclesCount = $this->maxCycles;

        $coprocsPool = array();
        $streamsPool = array();

        for ($i = 0; $i < $maxCoprocsCount; $i++) {
            if (($data = $messageProducer()) !== false) {
                $data = array($data);

                $j = 1;
                while ($j++ < $maxMessagesCount && ($tmpData = $messageProducer()) !== false) {
                    $data[] = $tmpData;
                }

                $coproc = $this->createCoproc($cliCommand);

                /** @var CoprocMaster $coprocMaster */
                $coprocMaster = $coproc['master'];

                $coproc['streamIndex'] = count($streamsPool);
                $streamsPool[] = $coprocMaster->getInputStream();

                $coprocsPool[(int)$coprocMaster->getInputStream()] = $coproc;

                $coprocMaster->writeMessage($data);
            } else {
                break;
            }
        }

        while (!empty($coprocsPool)) {

            $w = $e = array();
            $testStreams = $streamsPool;

            //Block until not receive some data from $streams
            stream_select($testStreams, $w, $e, null);

            foreach ($testStreams as $stream) {
                $coproc = $coprocsPool[(int)$stream];

                /** @var CoprocMaster $coprocMaster */
                $coprocMaster = $coproc['master'];

                $resultCollector($coprocMaster->readMessage());

                if (($data = $messageProducer()) !== false) {

                    $data = array($data);
                    $i = 1;
                    while ($i++ < $maxMessagesCount && ($tmpData = $messageProducer()) !== false) {
                        $data[] = $tmpData;
                    }

                    if (++$coproc['count'] >= $maxCyclesCount) {
                        unset($coprocsPool[(int)$stream]);
                        $coprocMaster->close();

                        $streamIndex = $coproc['streamIndex'];

                        $newCoproc = $this->createCoproc($cliCommand);
                        $newCoproc['streamIndex'] = $streamIndex;
                        /** @var CoprocMaster $newCoprocMaster */
                        $newCoprocMaster = $newCoproc['master'];
                        $coprocsPool[(int)$newCoprocMaster->getInputStream()] = $newCoproc;
                        $streamsPool[$streamIndex] = $newCoprocMaster->getInputStream();

                        $coproc = $newCoproc;

                    }

                    /** @var CoprocMaster $coprocMaster */
                    $coprocMaster = $coproc['master'];
                    $coprocMaster->writeMessage($data);

                } else {
                    unset($coprocsPool[(int)$stream]);
                    unset($streamsPool[$coproc['streamIndex']]);
                    $coprocMaster->close();
                }
            }
        }
    }

    private function createCoproc($cmd)
    {
        $coprocMaster = new CoprocMaster();
        $coprocMaster->start($cmd);
        return array('master' => $coprocMaster, 'count' => 0);
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * @return int
     */
    public function getMaxMessages()
    {
        return $this->maxMessages;
    }

    /**
     * @param int $maxMessages
     */
    public function setMaxMessages($maxMessages)
    {
        $this->maxMessages = $maxMessages;
    }

    /**
     * @return int
     */
    public function getMaxCycles()
    {
        return $this->maxCycles;
    }

    /**
     * @param int $maxCycles
     */
    public function setMaxCycles($maxCycles)
    {
        $this->maxCycles = $maxCycles;
    }

    /**
     * @param string $cliCommand
     */
    public function setCliCommand($cliCommand)
    {
        $this->cliCommand = $cliCommand;
    }

    /**
     * @param callable $messageProducer
     */
    public function setMessageProducer(callable $messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    /**
     * @param callable $resultCollector
     */
    public function setResultCollector(callable $resultCollector)
    {
        $this->resultCollector = $resultCollector;
    }


}