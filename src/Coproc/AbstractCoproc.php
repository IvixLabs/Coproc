<?php
namespace IvixLabs\Coproc;


abstract class AbstractCoproc
{
    const MESSAGE_EXIT = 'exit';
    const MESSAGE_OK = 'ok';
    const MESSAGE_DATA = 'data';

    protected $initialized = false;

    /**
     * @var resource
     */
    protected $procResource;

    /**
     * @var resource
     */
    protected $inputStream;

    /**
     * @var resource
     */
    protected $outputStream;

    /**
     * @var resource
     */
    protected $notifyStream;

    /**
     * @var resource
     */
    protected $stdoutStream;

    /**
     * @var resource
     */
    protected $stderrStream;


    /**
     * @var resource[]
     */
    protected $streams;


    /**
     * @param callable $callback
     * @throws \Exception
     * @return mixed|null
     */
    public function readMessage(callable $callback = null)
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Co-process must be initialized');
        }

        if($this->stdoutStream !== null) {
            $data = stream_get_contents($this->stdoutStream);
            if (!empty($data)) {
                echo $data;
            }
        }

        if($this->stderrStream !== null) {
            $data = stream_get_contents($this->stderrStream);
            if (!empty($data)) {
                $this->initialized = false;
                throw new \Exception($data);
            }
        }

        while (list($bytes) = fscanf($this->inputStream, "%s\n")) {
            if ($bytes == self::MESSAGE_EXIT) {
                fwrite($this->outputStream, self::MESSAGE_OK . "\n");

                fclose($this->inputStream);
                fclose($this->outputStream);
                break;
            }
            $data = '';
            $currentLength = 0;
            while ($tmpData = fread($this->inputStream, $bytes - $currentLength)) {
                $data .= $tmpData;
                $currentLength = strlen($data);
                if ($currentLength == $bytes) {
                    break;
                }
            }
            $messages = unserialize($data);

            if ($callback !== null) {
                $message = $callback($messages, $this);
                if ($message !== false) {
                    $this->writeMessage($message);
                }
            } else {
                return $messages;
            }
        }

        return null;
    }

    /**
     * @param $msg
     */
    public function writeMessage($msg)
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Co-process must be initialized');
        }

        if ($this->notifyStream) {
            fwrite($this->notifyStream, self::MESSAGE_DATA);
        }

        $data = serialize($msg);
        $length = strlen($data);

        fwrite($this->outputStream, $length . "\n");
        while ($bytes = fwrite($this->outputStream, $data)) {
            if ($bytes < $length) {
                $length -= $bytes;
                $data = substr($data, $bytes);
            } else {
                break;
            }
        }
    }

    /**
     * @return resource[]
     */
    public function getStreams()
    {
        return $this->streams;
    }
}
