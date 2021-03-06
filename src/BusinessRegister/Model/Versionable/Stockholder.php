<?php


namespace ByrokratSk\BusinessRegister\Model\Versionable;


use ByrokratSk\BusinessRegister\Model\Address;
use ByrokratSk\BusinessRegister\Model\Versionable;
use ByrokratSk\Helper\Arrayable;
use ByrokratSk\Helper\DateHelper;

class Stockholder extends Versionable implements \JsonSerializable, Arrayable
{
    public string $Name;
    public Address $Address;

    public function __construct($Name, $Address)
    {
        $this->Name = $Name;
        $this->Address = $Address;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->Name,
            'address' => is_null($this->Address) ? null : $this->Address->toArray(),
            'valid_from' => DateHelper::formatYmd($this->ValidFrom),
            'valid_to' => DateHelper::formatYmd($this->ValidTo),
        ];
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
