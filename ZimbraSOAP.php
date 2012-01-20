<?php

/**
 * Description of ZimbraSOAP
 *
 * @author LiberSoft <info@libersoft.it>
 */
class ZimbraSOAP
{

    // The entire XML message
    private $message;
    // Pointing to the context element
    private $context;
    // used for generating the filename of xml log dump
    private $lastRequestName;

    private $curlHandle;

    public function __construct($server, $port)
    {
        $this->curlHandle = curl_init();
        curl_setopt($this->curlHandle, CURLOPT_URL, "https://$server:$port/service/admin/soap");
        curl_setopt($this->curlHandle, CURLOPT_POST, TRUE);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYHOST, FALSE);

        $this->message = new SimpleXMLElement('<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope"></soap:Envelope>');
        $this->context = $this->message->addChild('Header')->addChild('context', null, 'urn:zimbra');
        $this->message->addChild('Body');
    }

    public function getXml()
    {
        return $this->message->asXml();
    }

    public function addContextChild($name, $value)
    {
        if (isset($this->context->$name)) {
            $this->context->$name = $value;
        } else {
            $this->context->addChild($name, $value);
        }
    }

    public function request($name, $value = null, $ns = null, $attributes = array(), $params = array())
    {
        $this->lastRequestName = $name;
        unset($this->message->children('soap', true)->Body);
        $body = $this->message->addChild('Body');
        $newChild = $body->addChild($name, $value, $ns);

        foreach ($attributes as $key => $value) {
            $newChild->addAttribute($key, $value);
        }

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                switch ($key) {
                    case 'attributes':
                        foreach ($value as $l => $b) {
                            $newParam = $newChild->addChild('a', $b);
                            $newParam->addAttribute('n', $l);
                        }
                        break;
                    default:
                        $newParam = $newChild->addChild($key, $value['_']);
                        unset($value['_']);
                        foreach ($value as $l => $b) {
                            $newParam->addAttribute($l, $b);
                        }
                }
            } else {
                $newChild->addChild($key, $value);
            }
        }

        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->getXml());
        return $this->processReply(curl_exec($this->curlHandle));
    }

    /**
     * Process a SOAP reply from Zimbra.
     *
     * @param a raw xml soap message
     * @return body content as SimpleXMLElement
     */
    private function processReply($soapMessage)
    {
        if (!$soapMessage) {
            throw new Exception(curl_error($this->curlHandle), curl_errno($this->curlHandle));
        }

        $xml = new SimpleXMLElement($soapMessage);
        $xml->asXML(sfConfig::get('sf_log_dir') . '/lastResponse-'.$this->lastRequestName.'.xml'); // debug

        $fault = $xml->children('soap', true)->Body->Fault;
        if ($fault) {
            throw new ZimbraException($fault->Detail->children()->Error->Code);
        }

        return $xml->children('soap', true)->Body;
    }

}