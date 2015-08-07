<?php
namespace IvixLabs\Coproc;


class CoprocMaster extends AbstractCoproc
{

    public function start($cmd, $notifyStream = false, array $streams = array())
    {
        if ($this->initialized) {
            throw new \RuntimeException('Co-process must be not initialized');
        }


        $desc = array(
            0 => array('pipe', 'rb'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
            3 => array('pipe', 'wb'),
        );

        if ($notifyStream) {
            $notifyStreamIndex = count($desc);
            $desc[$notifyStreamIndex] = array('pipe', 'wb');
        } else {
            $notifyStreamIndex = null;
        }

        $streamsIndexes = array();
        foreach ($streams as $stream) {
            $streamsIndexes[] = count($desc);
            $desc[] = $stream;
        }

        $proc = proc_open($cmd, $desc, $pipes);

        $this->inputStream = $pipes[3];
        $this->outputStream = $pipes[0];

        $this->stdoutStream = $pipes[1];
        stream_set_blocking($this->stdoutStream, false);
        $this->stderrStream = $pipes[2];
        stream_set_blocking($this->stderrStream, false);

        if ($notifyStream) {
            $this->notifyStream = $pipes[4];
        }
        $this->procResource = $proc;

        $this->initialized = true;

        $this->writeMessage(array(
            'notifyStream' => $notifyStreamIndex,
            'streams' => $streamsIndexes
        ));

        return true;
    }

    public function close()
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Co-process must be initialized');
        }

        $status = proc_get_status($this->procResource);
        if ($status === false) {
            throw new \RuntimeException('Co-process has not status');
        }
        $pid = $status['pid'];

        fwrite($this->outputStream, self::MESSAGE_EXIT . "\n");
        list($msg) = fscanf($this->inputStream, "%s\n");
        if ($msg !== self::MESSAGE_OK) {
            throw new \RuntimeException('Cant close process with pid ' . $pid);
        }

        proc_close($this->procResource);

        $this->initialized = false;
    }

    /**
     * @return resource
     */
    public function getInputStream()
    {
        return $this->inputStream;
    }

    /**
     * @return resource
     */
    public function getNotifyStream()
    {
        return $this->notifyStream;
    }

    function __destruct()
    {
        if ($this->initialized) {
            $this->close();
        }
    }
}