<?php

namespace AerialShip\LightSaml\Meta;

use AerialShip\LightSaml\Bindings;
use AerialShip\LightSaml\Error\BuildRequestException;
use AerialShip\LightSaml\Model\Metadata\EntityDescriptor;
use AerialShip\LightSaml\Model\Metadata\IdpSsoDescriptor;
use AerialShip\LightSaml\Model\Metadata\Service\AssertionConsumerService;
use AerialShip\LightSaml\Model\Metadata\SpSsoDescriptor;
use AerialShip\LightSaml\Model\Protocol\Message;
use AerialShip\LightSaml\Protocol;


abstract class AbstractRequestBuilder
{

    /** @var EntityDescriptor */
    protected $edSP;

    /** @var EntityDescriptor */
    protected $edIDP;

    /** @var \AerialShip\LightSaml\Meta\SpMeta */
    protected $spMeta;



    /**
     * @param EntityDescriptor $edSP
     * @param EntityDescriptor $edIDP
     * @param SpMeta $spMeta
     */
    public function __construct(EntityDescriptor $edSP, EntityDescriptor $edIDP, SpMeta $spMeta)
    {
        $this->edSP = $edSP;
        $this->edIDP = $edIDP;
        $this->spMeta = $spMeta;
    }


    /**
     * @param Message $message
     * @return \AerialShip\LightSaml\Binding\Response
     */
    abstract public function send(Message $message);


    /**
     * @param EntityDescriptor $edIDP
     */
    public function setEdIDP($edIDP) {
        $this->edIDP = $edIDP;
    }

    /**
     * @return EntityDescriptor
     */
    public function getEdIDP() {
        return $this->edIDP;
    }

    /**
     * @param EntityDescriptor $edSP
     */
    public function setEdSP($edSP) {
        $this->edSP = $edSP;
    }

    /**
     * @return EntityDescriptor
     */
    public function getEdSP() {
        return $this->edSP;
    }




    /**
     * @return SpSsoDescriptor
     * @throws BuildRequestException
     */
    protected function getSpSsoDescriptor()
    {
        $ed = $this->getEdSP();
        if (!$ed) {
            throw new BuildRequestException('No SP EntityDescriptor set');
        }
        $arr = $ed->getAllSpSsoDescriptors();
        if (empty($arr)) {
            throw new BuildRequestException('SP EntityDescriptor has no SPSSODescriptor');
        }
        if (count($arr)>1) {
            throw new BuildRequestException('SP EntityDescriptor has more then one SPSSODescriptor');
        }
        $result = $arr[0];
        return $result;
    }


    /**
     * @return IdpSsoDescriptor
     * @throws BuildRequestException
     */
    protected function getIdpSsoDescriptor()
    {
        $ed = $this->getEdIDP();
        if (!$ed) {
            throw new BuildRequestException('No IDP EntityDescriptor set');
        }
        $arr = $ed->getAllIdpSsoDescriptors();
        if (empty($arr)) {
            throw new BuildRequestException('IDP EntityDescriptor has no IDPSSODescriptor');
        }
        if (count($arr)>1) {
            throw new BuildRequestException('IDP EntityDescriptor has more then one IDPSSODescriptor');
        }
        $result = $arr[0];
        return $result;
    }


    /**
     * @return AssertionConsumerService
     * @throws BuildRequestException
     */
    protected function getAssertionConsumerService()
    {
        $sp = $this->getSpSsoDescriptor();
        $arr = $sp->findAssertionConsumerServices();
        if (empty($arr)) {
            throw new BuildRequestException('SPSSODescriptor has not AssertionConsumerService');
        }
        $result = null;
        foreach ($arr as $asc) {
            if (Bindings::getBindingProtocol($asc->getBinding()) == Protocol::SAML2) {
                $result = $asc;
                break;
            }
        }
        if (!$result) {
            throw new BuildRequestException('SPSSODescriptor has no SAML2 AssertionConsumerService');
        }
        return $result;
    }
} 