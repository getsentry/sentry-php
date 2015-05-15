<?php
/**
 * Asterisk out passwords from password fields in frames, http,
 * and basic extra data.
 *
 * @package raven
 */
class Raven_SanitizeDataProcessor extends Raven_Processor
{
    const MASK = '********';
    const FIELDS_RE = '/(authorization|password|passwd|secret|password_confirmation|card_number|auth_pw)/i';
    const VALUES_RE = '/^(?:\d[ -]*?){13,16}$/';

    private $client;
    private $fields_re;
    private $values_re;

    public function __construct(Raven_Client $client)
    {
        $this->client       = $client;
        $this->fields_re    = self::FIELDS_RE;
        $this->values_re   = self::VALUES_RE;
    }

    /**
     * Override the default processor options
     *
     * @param array $options    Associative array of processor options
     */
    public function setProcessorOptions(array $options)
    {
        if (isset($options['fields_re'])) {
            $this->fields_re = $options['fields_re'];
        }

        if (isset($options['values_re'])) {
            $this->values_re = $options['values_re'];
        }
    }

    public function process(&$data)
    {
        foreach ($data as $k => &$v) {
            if (preg_match($this->fields_re, $k)) {
                if (is_array($v)) {
                    array_walk_recursive($v, function (&$item, $key) {
                        $item = Raven_SanitizeDataProcessor::MASK;
                    });
                } else {
                    $v = self::MASK;
                }
            } elseif (is_array($v)) {
                $this->process($v);
            } elseif (preg_match($this->values_re, $v)) {
                $v = self::MASK;
            }
        }
    }

    /**
     * @return string
     */
    public function getFieldsRe()
    {
        return $this->fields_re;
    }

    /**
     * @param string $fields_re
     */
    public function setFieldsRe($fields_re)
    {
        $this->fields_re = $fields_re;
    }

    /**
     * @return string
     */
    public function getValuesRe()
    {
        return $this->values_re;
    }

    /**
     * @param string $values_re
     */
    public function setValuesRe($values_re)
    {
        $this->values_re = $values_re;
    }
}
