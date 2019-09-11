<?php

namespace App\Http\Controllers;

use App\Exceptions\ConnectionException;
use App\Http\Resources\TransactionResource;
use App\Rules\HexcellCode;
use App\Services\Constants;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\HexcellClient;
use App\Http\Resources\MeterResource;
use App\Exceptions\ResourceNotFoundException;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\Redis;

class MeterController extends Controller
{
    /**
     * @var HexcellClient
     */
    protected $client;

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var
     */
    protected $repository;

    /**
     * Create a new controller instance.
     *
     * @param HexcellClient $client
     * @param TransactionRepository $repository
     */
    public function __construct(HexcellClient $client, TransactionRepository $repository)
    {
        $this->client     = $client;
        $this->repository = $repository;
    }

    /**
     * @param Request $request
     * @return MeterResource
     * @throws ResourceNotFoundException
     * @throws \App\Exceptions\BusinessErrorException
     * @throws \App\Exceptions\UnAuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function search(Request $request)
    {

        \Log::info('MeterController: New search request', [
            'input' => $request->input(),
            'ip'    => $request->getClientIp(),
        ]);

        $this->validate($request, ['meterCode' => ['required', 'string', new HexcellCode()]]);

        $meter = $this->client->searchMeter($request['meterCode']);

        \Log::info('MeterController: Search Successful', [
            'meter_code'        => $meter->getMeterCode(),
            'tenant' => $meter->getLandlord(),
        ]);

        \Cache::put($meter->getInternalId(), $meter, Carbon::now()->addMinutes(10));

        \Log::info('MeterController: Meter saved to cache successfully', [
            'meter_code'        => $meter->getMeterCode(),
            'internal_id' => $meter->getInternalId(),
        ]);

        return new MeterResource($meter);

    }

    /**
     * @param Request $request
     * @return TransactionResource
     * @throws ConnectionException
     * @throws ResourceNotFoundException
     * @throws \App\Exceptions\BusinessErrorException
     * @throws \App\Exceptions\TokenGenerationException
     * @throws \App\Exceptions\UnAuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function token(Request $request)
    {
        \Log::info('MeterController: New token request', [
            'input' => $request->input(),
            'ip'    => $request->getClientIp()
        ]);

        $this->validate($request, [
                'internalId'  => ['required', 'string',],
                'amount'      => ['required', 'regex:/^\d+(\.\d{1,2})?$/',],
                'externalId'  => ['required', 'string']
            ]
        );

        $meter = \Cache::get($request['internalId']);

        $transaction = $this->repository->create($meter, $request->input());

        \Cache::forget($request['internalId']);

        $token = $this->client->generateToken([
            'meterId' => $transaction->meter_id,
            'amount'  => $transaction->amount,
            'energy'  => $transaction->energy
        ]);

        $transaction->token  = $token;
        $transaction->status = Constants::SUCCESS;
        if ($transaction->save()) {

            \Log::info('MeterController: Token generated successfully', [
                'token'       => $transaction->token,
                'meter code'  => $transaction->meter_code,
                'internal id' => $transaction->internal_id,
                'amount'      => $transaction->amount,
                'energy'      => $transaction->energy
            ]);


            return new TransactionResource($transaction);

        }

        throw new ConnectionException('mysql', 'save');
    }

}
