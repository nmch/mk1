<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Log_Aws_Sns implements Logic_Interface_Log_Driver
{
    private $config = [];
    private $sns_client;
    private $topic_arn;

    function __construct($config)
    {
        $this->config = $config;
        $this->sns_client = (new \Aws_Sdk())->get_sns_client();
        $this->topic_arn = $this->config['topic_arn'] ?? null;
    }

    function write($data)
    {
        if ($this->topic_arn) {
            try {
                if (Arr::get($this->config, 'add_uniqid')) {
                    $data['uniqid'] = Arr::get($data, "config.uniqid");
                }
                unset($data['config']);
                $str = json_encode($data, JSON_HEX_TAG
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
                    | JSON_HEX_AMP
                    | JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                $this->sns_client->publish([
                    'TopicArn' => $this->topic_arn,
                    'Message' => $str,
                ]);
            } catch (Exception $e) {
                // nop
            }
        }
    }
}
