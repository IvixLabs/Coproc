<?php
namespace IvixLabs\Coproc;


abstract class AbstractCoproc
{
    const MESSAGE_EXIT = 'exit';
    const MESSAGE_OK = 'ok';

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
     * @param callable $callback
     * @return mixed|null
     */
    public function readMessage(\Closure $callback = null)
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Co-process must be initialized');
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


}