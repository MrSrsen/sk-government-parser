<?php

namespace SkGovernmentParser\DataSources\BusinessRegister\Parser;


use SkGovernmentParser\DataSources\BusinessRegister\Model\Address;
use \SkGovernmentParser\DataSources\BusinessRegister\Model\BusinessSubject;
use SkGovernmentParser\DataSources\BusinessRegister\Model\SubjectCapital;
use SkGovernmentParser\DataSources\BusinessRegister\Model\SubjectContributor;
use SkGovernmentParser\DataSources\BusinessRegister\Model\SubjectManager;
use SkGovernmentParser\DataSources\BusinessRegister\Model\SubjectPartner;
use SkGovernmentParser\DataSources\BusinessRegister\Model\SubjectSeat;
use SkGovernmentParser\DataSources\BusinessRegister\Model\TextDatePair;
use SkGovernmentParser\Helper\DomHelper;
use SkGovernmentParser\Helper\StringHelper;


class BusinessSubjectPageParser
{

    public static function parseHtml(string $rawHtml): BusinessSubject
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML($rawHtml); // Do not throw notices

        $htmlBody = $doc->childNodes[1]->childNodes[1];

        $infoTables = [];
        /** @var \DOMElement $bodyNode */
        foreach ($htmlBody->childNodes as $bodyNode) {
            if ($bodyNode->nodeName === "table" && $bodyNode->getAttribute("cellspacing") === "3") {
                $infoTables[] = self::getInfoTables($bodyNode);
            }
        }

        $subjectInfo = [
            'business_register_id' => null, // TODO: From <a> link to slovak version
            'business_name' => null,
            'district_court' => null,
            'section' => null,
            'insert_number' => null,
            'registered_seat' => null,
            'identification_number' => null,
            'date_of_entry' => null,
            'legal_form' => null,
            'company_objects' => null,
            'partners' => null,
            'members_contribution' => null,
            'management_body' => null,
            'acting_in_the_name' => null,
            'capital' => null,
            'other_legal_facts' => null,
            'updated_at' => null,
            'extracted_at' => null,
        ];

        $subjectInfo['business_register_id'] = StringHelper::stringBetween(
            $htmlBody->childNodes[1]->childNodes[0]->childNodes[2]->childNodes[6]->childNodes[3]->getAttribute("href"),
            'ID=',
            '&'
        );
        $subjectInfo['district_court'] = str_replace('Extract from the Business Register of the District Court ', '',
            trim($htmlBody->childNodes[3]->childNodes[0]->textContent));
        $subjectInfo['section'] = trim($htmlBody->childNodes[5]->childNodes[0]->childNodes[0]->childNodes[3]->textContent);
        $subjectInfo['insert_number'] = trim($htmlBody->childNodes[5]->childNodes[0]->childNodes[2]->childNodes[3]->textContent);

        foreach ($infoTables as $infoTable) {
            switch ($infoTable->tableTitle) {
                case 'Business name': {
                    $subjectInfo["business_name"] = self::parseSimpleInfoTable($infoTable);
                    break;
                }
                case 'Registered seat': {
                    $subjectInfo["registered_seat"] = (object)[
                        'address' => (object)[
                            'city' => trim($infoTable->subTables[0]->table->childNodes[5]->textContent),
                            'zip_code' => StringHelper::removeWhitespaces($infoTable->subTables[0]->table->childNodes[7]->textContent),
                            'street_name' => trim($infoTable->subTables[0]->table->childNodes[1]->textContent),
                            'street_number' => trim($infoTable->subTables[0]->table->childNodes[3]->textContent),
                        ],
                        'date' => $infoTable->subTables[0]->date
                    ];
                    break;
                }
                case 'Identification number (IČO)': {
                    $subjectInfo["identification_number"] = self::parseSimpleInfoTable($infoTable);
                    $subjectInfo["identification_number"]->text = StringHelper::removeWhitespaces($subjectInfo["identification_number"]->text);
                    break;
                }
                case 'Date of entry': {
                    $subjectInfo["date_of_entry"] = self::parseSimpleInfoTable($infoTable);
                    break;
                }
                case 'Legal form': {
                    $subjectInfo["legal_form"] = self::parseSimpleInfoTable($infoTable);
                    break;
                }
                case 'Objects of the company': {
                    $objects = [];
                    foreach ($infoTable->subTables as $subTable) {
                        $objects[] = (object)[
                            'text' => trim($subTable->table->textContent),
                            'date' => $subTable->date
                        ];
                    }
                    $subjectInfo['company_objects'] = $objects;
                    break;
                }
                case 'Partners': {
                    $partners = [];
                    foreach ($infoTable->subTables as $subTable) {
                        $textElements = [];
                        foreach (DomHelper::nodeListToArray($subTable->table->childNodes) as $subTableNode) {
                            if ($subTableNode->nodeName === "span" && $subTableNode->getAttribute("class") === "ra") {
                                $textElements[] = $subTableNode;
                            }
                        }

                        $partners[] = (object)[
                            'name' => trim($textElements[0]->textContent),
                            'address' => (object)[
                                'street_name' => trim($textElements[1]->textContent),
                                'street_number' => trim($textElements[2]->textContent),
                                'city' => trim($textElements[3]->textContent),
                                'zip_code' => StringHelper::removeWhitespaces($textElements[4]->textContent),
                            ],
                            'date' => $subTable->date
                        ];
                    }

                    $subjectInfo['partners'] = $partners;
                    break;
                }
                case 'Contribution of each member': {
                    $contributions = [];
                    foreach ($infoTable->subTables as $subTable) {
                        $contributions[] = (object)[
                            'contributor_name' => trim($subTable->table->childNodes[1]->textContent),
                            'amount' => (float)StringHelper::removeWhitespaces(str_replace('Amount of investment: ', '', $subTable->table->childNodes[3]->textContent)),
                            'paid' => (float)StringHelper::removeWhitespaces(str_replace('Paid up: ', '', $subTable->table->childNodes[7]->textContent)),
                            'currency' => trim($subTable->table->childNodes[5]->textContent),
                            'date' => $subTable->date,
                        ];
                    }
                    $subjectInfo['members_contribution'] = $contributions;
                    break;
                }
                case 'Management body': {
                    $management = [];
                    foreach ($infoTable->subTables as $subTable) {
                        if (trim($subTable->table->textContent) === 'konatelia') {
                            continue; // Skip header table cell
                        }

                        $management[] = (object)[
                            'first_name' => trim($subTable->table->childNodes[0]->childNodes[1]->textContent),
                            'last_name' => trim($subTable->table->childNodes[0]->childNodes[3]->textContent),
                            'address' => (object)[
                                'street_name' => trim($subTable->table->childNodes[2]->textContent),
                                'street_number' => trim($subTable->table->childNodes[4]->textContent),
                                'city' => trim($subTable->table->childNodes[6]->textContent),
                                'zip_code' => StringHelper::removeWhitespaces($subTable->table->childNodes[8]->textContent),
                            ],
                            'date' => $subTable->date,
                        ];
                    }
                    $subjectInfo['management_body'] = $management;
                    break;
                }
                case 'Acting in the name of the company': {
                    $subjectInfo["acting_in_the_name"] = self::parseSimpleInfoTable($infoTable);
                    break;
                }
                case 'Capital': {
                    $subjectInfo["capital"] = (object)[
                        'total' => (float)StringHelper::removeWhitespaces($infoTable->subTables[0]->table->childNodes[1]->textContent),
                        'paid' => (float)StringHelper::removeWhitespaces(
                            str_replace('Paid up: ', '', $infoTable->subTables[0]->table->childNodes[5]->textContent)
                        ),
                        'currency' => StringHelper::removeWhitespaces($infoTable->subTables[0]->table->childNodes[3]->textContent),
                        'date' => $infoTable->subTables[0]->date
                    ];
                    break;
                }
                case 'Other legal facts': {
                    $facts = [];
                    foreach ($infoTable->subTables as $subTable) {
                        $facts[] = (object)[
                            'text' => trim($subTable->table->textContent),
                            'date' => $subTable->date
                        ];
                    }
                    $subjectInfo['other_legal_facts'] = $facts;
                    break;
                }
                default:
                    break; // ignore -> not implemented
            }
        }

        $lastTable = null;
        foreach ($htmlBody->childNodes as $bodyNode) {
            if ($bodyNode->nodeName === 'table') $lastTable = $bodyNode;
        }
        $subjectInfo['updated_at'] = StringHelper::removeWhitespaces($lastTable->childNodes[0]->childNodes[2]->textContent);
        $subjectInfo['extracted_at'] = StringHelper::removeWhitespaces($lastTable->childNodes[1]->childNodes[2]->textContent);

        return new BusinessSubject(
            $subjectInfo['business_register_id'],
            TextDatePair::fromObject($subjectInfo['business_name']),
            $subjectInfo['district_court'],
            $subjectInfo['section'],
            $subjectInfo['insert_number'],
            new SubjectSeat(
                new Address(
                    $subjectInfo['registered_seat']->address->street_name,
                    $subjectInfo['registered_seat']->address->street_number,
                    $subjectInfo['registered_seat']->address->city,
                    $subjectInfo['registered_seat']->address->zip_code,
                ),
                $subjectInfo['registered_seat']->date
            ),
            TextDatePair::fromObject($subjectInfo['identification_number']),
            TextDatePair::fromObject($subjectInfo['legal_form']),
            TextDatePair::fromObject($subjectInfo['acting_in_the_name']),
            new SubjectCapital(
                $subjectInfo['capital']->total,
                $subjectInfo['capital']->paid,
                $subjectInfo['capital']->currency,
                $subjectInfo['capital']->date,
            ),
            array_map(function ($rawObject) {
                return new TextDatePair($rawObject->text, $rawObject->date);
            }, $subjectInfo['company_objects']),
            array_map(function ($rawPartner) {
                return new SubjectPartner(
                    $rawPartner->name,
                    new Address(
                        $rawPartner->address->street_name,
                        $rawPartner->address->street_number,
                        $rawPartner->address->city,
                        $rawPartner->address->zip_code
                    ),
                    $rawPartner->date
                );
            }, $subjectInfo['partners']),
            array_map(function ($rawContributor) {
                return new SubjectContributor(
                    $rawContributor->contributor_name,
                    $rawContributor->amount,
                    $rawContributor->paid,
                    $rawContributor->currency,
                    $rawContributor->date,
                );
            }, $subjectInfo['members_contribution']),
            array_map(function ($rawManager) {
                return new SubjectManager(
                    $rawManager->first_name,
                    $rawManager->last_name,
                    new Address(
                        $rawManager->address->street_name,
                        $rawManager->address->street_number,
                        $rawManager->address->city,
                        $rawManager->address->zip_code,
                    ),
                    $rawManager->date
                );
            }, $subjectInfo['management_body']),
            array_map(function ($rawFact) {
                return new TextDatePair($rawFact->text, $rawFact->date);
            }, $subjectInfo['other_legal_facts']),
            new \DateTime($subjectInfo['date_of_entry']->text),
            new \DateTime($subjectInfo['updated_at']),
            new \DateTime($subjectInfo['extracted_at'])
        );
    }

    private static function getInfoTables(\DOMElement $infoTable): object
    {
        $leftText = self::trimInfoTableText($infoTable->childNodes[0]->childNodes[0]->childNodes[1]->textContent);

        $subTables = [];
        foreach ($infoTable->childNodes[0]->childNodes[2]->childNodes as $subtable) {
            $subTables[] = (object)[
                'table' => $subtable->childNodes[0]->childNodes[0],
                'date' => new \DateTime(
                    StringHelper::stringBetween(
                        trim($subtable->childNodes[0]->childNodes[2]->textContent),
                        '(from: ',
                        ')'),
                )
            ];
        }

        return (object) [
            'tableTitle' => $leftText,
            'subTables' => $subTables
        ];
    }

    private static function parseSimpleInfoTable(object $infoTable): object
    {
        return (object)[
            'text' => trim($infoTable->subTables[0]->table->textContent),
            'date' => $infoTable->subTables[0]->date
        ];
    }

    private static function trimInfoTableText(string $text): string
    {
        return trim($text, ": ".StringHelper::NON_BREAKING_SPACE);
    }
}