<?php

namespace SkGovernmentParser\DataSources\BusinessRegister\Model;


class BusinessSubject
{
    public int $BusinessRegisterId;

    public TextDatePair $BusinessName;
    public string $InsertNumber;
    public SubjectSeat $RegisteredSeat;
    public TextDatePair $IdentificationNumber;
    public TextDatePair $LegalForm;
    public TextDatePair $ActingInTheName;

    public string $DistrictCourt;
    public string $Section;

    public SubjectCapital $Capital;

    public array $CompanyObjects;
    public array $Partners;
    public array $MembersContribution;
    public array $ManagementBody;
    public array $OtherLegalFacts;

    public \DateTime $EntryDate;
    public \DateTime $UpdatedAt;
    public \DateTime $ExtractedAt;


    public function __construct(
        int $BusinessRegisterId,
        TextDatePair $BusinessName,
        string $DistrictCourt,
        string $Section,
        string $InsertNumber,
        SubjectSeat $RegisteredSeat,
        TextDatePair $IdentificationNumber,
        TextDatePair $LegalForm,
        TextDatePair $ActingInTheName,
        SubjectCapital $Capital,
        array $CompanyObjects,
        array $Partners,
        array $MembersContribution,
        array $ManagementBody,
        array $OtherLegalFacts,
        \DateTime $EntryDate,
        \DateTime $UpdatedAt,
        \DateTime $ExtractedAt
    ) {
        $this->BusinessRegisterId = $BusinessRegisterId;
        $this->BusinessName = $BusinessName;
        $this->DistrictCourt = $DistrictCourt;
        $this->Section = $Section;
        $this->InsertNumber = $InsertNumber;
        $this->RegisteredSeat = $RegisteredSeat;
        $this->IdentificationNumber = $IdentificationNumber;
        $this->LegalForm = $LegalForm;
        $this->ActingInTheName = $ActingInTheName;
        $this->Capital = $Capital;
        $this->CompanyObjects = $CompanyObjects;
        $this->Partners = $Partners;
        $this->MembersContribution = $MembersContribution;
        $this->ManagementBody = $ManagementBody;
        $this->OtherLegalFacts = $OtherLegalFacts;
        $this->EntryDate = $EntryDate;
        $this->UpdatedAt = $UpdatedAt;
        $this->ExtractedAt = $ExtractedAt;
    }

}