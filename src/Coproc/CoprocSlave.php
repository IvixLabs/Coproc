<?php
namespace IvixLabs\Coproc;


class CoprocSlave extends AbstractCoproc
{

    /**
     * @var callable
     */
    private $callback;

    public function listen(callable $callback = null)
    {
        $this->inputStream = fopen('php://fd/0', 'rb');
        $this->outputStream = fopen('php://fd/3', 'wb');

        $this->initialized = true;

        $initData = $this->readMessage();

        $notifyStreamIndex = $initData['notifyStream'];
        if ($notifyStreamIndex !== null) {
            $this->notifyStream = fopen('php://fd/' . $notifyStreamIndex, 'wb');
        }

        $streamIndexes = $initData['streams'];
        foreach ($streamIndexes as $streamIndex) {
            $this->streams[] = $this->openStream($streamIndex);
        }

        if ($callback === null) {
            $callback = $this->callback;
        }
        $this->readMessage($callback);
    }

    /**
     * @param callable $callback
     */
    public function setCallback($callback)
    {
        $this->callback = $callback;
    }

    /**
     * @param $index
     * @return resource
     */
    public function openStream($index)
    {
        return fopen('php://fd/' . $index, 'wb');
    }


}