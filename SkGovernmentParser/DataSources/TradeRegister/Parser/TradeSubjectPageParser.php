<?php


namespace SkGovernmentParser\DataSources\TradeRegister\Parser;


use SkGovernmentParser\DataSources\TradeRegister\Model\Address;
use SkGovernmentParser\DataSources\TradeRegister\Model\BusinessObject;
use SkGovernmentParser\DataSources\TradeRegister\Model\Establishment;
use SkGovernmentParser\DataSources\TradeRegister\Model\TradeSubject;
use SkGovernmentParser\Helper\StringHelper;

class TradeSubjectPageParser
{

    public static function parseHtml(string $rawHtml): TradeSubject
    {
        $rawHtml = str_replace('<HEAD>', '<HEAD><meta charset="utf-8">', $rawHtml); // Fix for encoding
        $rawHtml = str_replace('<br/>', "<br/> ", $rawHtml); // Fix for spaces between words in address

        $doc = new \DOMDocument();
        @$doc->loadHTML($rawHtml); // Do not throw notices

        # ~

        $tradeSubject = [
            'identification_number' => null,
            'business_name' => null,
            'register_number' => null,
            'district_court' => null,
            'registered_seat' => null,
            'management' => null,
            'business_objects' => null,
            'terminated_at' => null,
            'extracted_at' => null
        ];

        $main = $doc->getElementById('panel1');
        /** @var \DOMElement $contentNode */
        foreach ($main->childNodes as $index => $contentNode) {
            if ($index === 0) {
                $tradeSubject['district_court'] = trim($contentNode->childNodes[0]->textContent);
                $tradeSubject['register_number'] = trim(str_replace('Číslo živnostenského registra:', '', $contentNode->childNodes[2]->textContent));
            } elseif ($contentNode->nodeName === 'dl') {
                $header = null;

                foreach ($contentNode->childNodes as $listItem) {
                    if ($listItem->tagName !== 'dt' && $listItem->tagName !== 'dd') {
                        continue; // ignore
                    }

                    if ($listItem->tagName === 'dt') {
                        $header = trim($listItem->textContent);
                    } else {
                        switch($header) {
                            case 'Obchodné meno': {
                                $tradeSubject['business_name'] = trim($listItem->textContent);
                                break;
                            }
                            case 'IČO': {
                                $tradeSubject['identification_number'] = trim($listItem->textContent);
                                break;
                            }
                            case 'Sídlo':
                            case 'Miesto podnikania': {
                                $rawAddress = trim($listItem->textContent);
                                $tradeSubject['registered_seat'] = self::parseAddress($rawAddress);
                                break;
                            }
                            case 'Štatutárny orgán': {
                                $tradeSubject['management'][] = (object)[
                                    'name' => trim($listItem->childNodes[0]->textContent),
                                    'address' => trim($listItem->childNodes[1]->textContent)
                                ];
                                break;
                            }
                            default: {
                                // ignore, not implemented
                                break;
                            }
                        }
                    }
                }
            } elseif ($contentNode->tagName === 'p' && StringHelper::str_contains($contentNode->textContent, 'ukončil podnikateľskú činnosť')) {
                $tradeSubject['terminated_at'] = self::parseDmyDate(str_replace('Podnikateľský subjekt ukončil podnikateľskú činnosť vo všetkých predmetoch podnikania uvedených na dokladoch o živnostenskom oprávnení ku dňu', '', $contentNode->textContent));
            } elseif ($contentNode->tagName === 'ol') {
                $businessObjects = [];
                foreach ($contentNode->childNodes as $listNode) {
                    $establishments = [];
                    $manager = null;

                    if (isset($listNode->childNodes[2])) {
                        $subListHeader = trim($listNode->childNodes[2]->childNodes[0]->textContent);
                        switch ($subListHeader) {
                            case 'Prevádzkarne': {
                                foreach ($listNode->childNodes[2]->childNodes as $index_2 => $establishment) {
                                    if ($index_2 === 0) {
                                        continue; // ignore header
                                    }
                                    $establishments[] = self::parseAddress(trim($establishment->textContent));
                                }
                                break;
                            }
                            case 'Zodpovedný zástupca': {
                                $manager = trim($listNode->childNodes[2]->childNodes[1]->textContent);
                                break;
                            }
                        }
                    }

                    $businessObjects[] = new BusinessObject(
                        trim($listNode->childNodes[0]->textContent),
                        self::parseDmyDate(str_replace('Deň vzniku oprávnenia:', '', $listNode->childNodes[1]->textContent)),
                        $manager,
                        empty($establishments) ? null : $establishments,
                    );
                }

                $tradeSubject['business_objects'] = empty($businessObjects) ? null : $businessObjects;
            }
        }

        $lastSection = $main->childNodes[count($main->childNodes) - 1];
        $tradeSubject['extracted_at'] = self::parseDmyDate(str_replace('Dátum výpisu:', '', $lastSection->textContent));

        return new TradeSubject(
            $tradeSubject['identification_number'],
            $tradeSubject['business_name'],
            $tradeSubject['register_number'],
            $tradeSubject['district_court'],
            $tradeSubject['registered_seat'],
            $tradeSubject['management'],
            $tradeSubject['business_objects'],
            $tradeSubject['extracted_at'],
            $tradeSubject['terminated_at']
        );
    }

    private static function parseAddress(string $rawAddress): Address
    {
        $commaSplit = explode(',', $rawAddress);

        $citySplit = explode(' ', $commaSplit[0]);
        if (count($citySplit) === 1) {
            $zip = null; // zip is not defined
            $city = $citySplit[0];
        } else {
            $zip = $citySplit[0]; // First part of "city" is zip
            unset($citySplit[0]);
            $city = implode(' ', $citySplit);
        }

        $streetSplit = explode(' ', $commaSplit[1]);
        $streetNumber = $streetSplit[count($streetSplit) - 1]; // Last "word" of street is number
        unset($streetSplit[count($streetSplit) - 1]);
        $streetName = implode(' ', $streetSplit);

        return new Address(
            trim($streetName),
            trim($streetNumber),
            trim($city),
            trim($zip)
        );
    }

    private static function parseDmyDate(string $rawDate): \DateTime
    {
        try {
            return \DateTime::createFromFormat('d.m.Y', trim($rawDate));
        } catch (\TypeError $exception) {
            print_r($rawDate); die("\n");
        }
    }
}
