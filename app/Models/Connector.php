<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Connector extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "connectors";
    protected $fillable = [
        'uuid',
        'name',
        'api_key',
        'api_secret',
        'connector_domain',
        'pcc',
        'printer',
        'iata',
        'client_ip',
        'is_enable',
        'supplier_id',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'is_enable' => 'boolean',
        ];
    }

    public function getConnectorCreds()
    {
        switch ($this->type) {
            case 'PIA_HITIT':
                return $this->getHititCreds();
                break;
            case 'SABRE':
                return $this->getSabreCreds();
            case 'AIRBLUE_API':
                return $this->getAirblueCreds();
            case 'FLYDUBAI_API':
                return $this->getFlyDubaiCreds();
            case 'ONEAPI':
                return $this->getFlyJinnahCreds();
                break;
        }
    }

    private function getHititCreds()
    {
        return [
            'client_ip_address' => $this->client_ip,
            'username' => $this->api_key,
            'password' => $this->api_secret,
        ];
    }

    private function getSabreCreds()
    {
        return [
            'api_key' => $this->api_key,
            'api_secret' => $this->api_secret,
            'pcc' => $this->pcc,
            'printer'=>$this->printer,
            'domain'=>$this->connector_domain
        ];
    }

    private function getAirblueCreds()
    {
        return [
            'ID'                => $this->api_key,
            'MessagePassword'   => $this->api_secret,
            'ERSP_UserID'       =>$this->connector_domain
        ];
    }

    private function getFlyDubaiCreds()
    {
        return [
            'api_key'       => $this->api_key,
            'api_secret'    => $this->api_secret,
            'username'      => $this->connector_domain,
            'password'      => $this->pcc,
            'iata_number'   => $this->printer
        ];
    }

    private function getFlyJinnahCreds()
    {
        return [
            'username'      => $this->api_key,
            'password'      => $this->api_secret,
            'agent_code'    => $this->connector_domain,
        ];
    }
}
