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

    protected $cookieFile = '/tmp/cookie.txt';

    /**
     * @param Meter $meter
     */
    public function __construct(Meter $meter)
    {
        $this->meter      = $meter;
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



        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_PORT => "8088",
            CURLOPT_URL => "http://58.60.230.219:8088/Common/GetRegMeterByMeterCode?id=$meterCode&nflag=1",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Accept: */*",
                "Accept-Encoding: gzip, deflate",
                "Cache-Control: no-cache",
                "Connection: keep-alive",
                "cache-control: no-cache"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new BusinessErrorException('Meter Search Error');
        }

        $response = json_decode($response);

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
     * @throws UnAuthorizationException
     */
    public function login()
    {
        $hexcellUrl = config('app.hexcell_credentials.url');
        $username   = config('app.hexcell_credentials.username');
        $password   = config('app.hexcell_credentials.password');


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_PORT => "8088",
            CURLOPT_URL => $hexcellUrl . "/Login/VerifyLoginUser?id=$username&pwd=$password",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new UnAuthorizationException("Login Error for [$username]");
        }

        sleep(2);
    }
}