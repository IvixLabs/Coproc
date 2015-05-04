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


}