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

        $notifyStream = fopen('php://fd/4', 'wb');
        if (is_resource($notifyStream)) {
            $close = (bool)fread($notifyStream, 1);
            if($close) {
                fclose($notifyStream);
            } else {
                $this->notifyStream = $notifyStream;
            }
        }

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