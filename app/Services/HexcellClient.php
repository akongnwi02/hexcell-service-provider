<?php
/**
 * Created by PhpStorm.
 * User: devert
 * Date: 8/27/19
 * Time: 9:34 PM
 */

namespace App\Services;

use App\Exceptions\BusinessErrorException;
use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\TokenGenerationException;
use App\Exceptions\UnAuthorizationException;
use GuzzleHttp\Exception\GuzzleException;

class HexcellClient
{
    /**
     * @var Meter
     */
    protected $meter;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * @param Meter $meter
     */
    public function __construct(Meter $meter)
    {
        $this->meter      = $meter;
        $this->httpClient = $this->getHttpClient();
    }

    /**
     * @param $meterCode
     * @return Meter
     * @throws ResourceNotFoundException
     * @throws UnAuthorizationException
     * @throws BusinessErrorException
     */
    public function searchMeter($meterCode)
    {
        $hexcellUrl = config('app.hexcell_credentials.url');

        $this->login();

        try {
            $response = $this->httpClient->request('GET', $hexcellUrl . HtmlSelectors::$MeterSearchUrl . "?id=$meterCode&nflag=1");
        } catch (GuzzleException $e) {
            throw new BusinessErrorException('Meter Search Error');
        }

        $response = json_decode($response->getBody()->getContents());

        if (empty($response)) {
            throw new ResourceNotFoundException(Meter::class, $meterCode);
        }

        $searchResult = $response[0];
        // build the meter object form the input fields
        $this->meter->setMeterCode($searchResult->FIDCode);
        $this->meter->setInternalId(md5(uniqid()));
        $this->meter->setAddress($searchResult->FAddr);
        $this->meter->setLandlord($searchResult->FName);
        $this->meter->setContractId($searchResult->FID);
        $this->meter->setTariff($searchResult->FPrice);
        $this->meter->setTariffType($searchResult->FUseTypeName);
        $this->meter->setArea($searchResult->FAreaName);
        $this->meter->setLastVendingDate($searchResult->FLastVendingDT);
        $this->meter->setRegistrationDate($searchResult->FRegDatetime);
        $this->meter->setVat($searchResult->FVAT);
        $this->meter->setMeterType($searchResult->FMeterTypeName);
        $this->meter->setPtct($searchResult->FPTCT);

        return $this->meter;
    }

    /**
     * @param array $params
     * @return string
     * @throws BusinessErrorException
     * @throws TokenGenerationException
     * @throws UnAuthorizationException
     */
    public function generateToken(array $params): string
    {
        $hexcellUrl = config('app.hexcell_credentials.url');

        $meter_id = $params['meterId'];
        $energy   = $params['energy'];
        $amount   = $params['amount'];

        $this->login();

        try {
            $response = $this->httpClient->request('GET', $hexcellUrl . HtmlSelectors::$TokenGenerateUrl . "?cid=$meter_id&amt=$amount&qt=$energy&nflag=1");
            $response = $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new BusinessErrorException('Meter Search Error');
        }

        if (strpos($response, 'true') !== false) {
            $token = explode(';', $response)[1];
            return $token;
        }

        throw new TokenGenerationException('Could not generate token');
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getHttpClient()
    {
        return new \GuzzleHttp\Client([
            'cookies' => true,
        ]);
    }

    /**
     * @throws UnAuthorizationException
     */
    public function login()
    {
        $hexcellUrl = config('app.hexcell_credentials.url');
        $username   = config('app.hexcell_credentials.username');
        $password   = config('app.hexcell_credentials.password');

        try {
            $this->httpClient->request('GET', $hexcellUrl . HtmlSelectors::$LoginUrl . "?id=$username&pwd=$password");
        } catch (GuzzleException $exception) {
            throw new UnAuthorizationException("Login Error for [$username]");
        }

        sleep(2);
    }
}