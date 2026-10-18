<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Bzst;

/**
 * The status codes of eVATR with the BZSt's category, HTTP status, field and
 * German message, as published by `GET /v1/info/statusmeldungen` on
 * 2026-10-18. A code that is not listed here arrives as its raw string (see
 * {@see Confirmation::$status}).
 */
enum EvatrStatus: string
{
    case Code0000 = 'evatr-0000';
    case Code0001 = 'evatr-0001';
    case Code0002 = 'evatr-0002';
    case Code0003 = 'evatr-0003';
    case Code0004 = 'evatr-0004';
    case Code0005 = 'evatr-0005';
    case Code0006 = 'evatr-0006';
    case Code0007 = 'evatr-0007';
    case Code0008 = 'evatr-0008';
    case Code0011 = 'evatr-0011';
    case Code0012 = 'evatr-0012';
    case Code0013 = 'evatr-0013';
    case Code1001 = 'evatr-1001';
    case Code1002 = 'evatr-1002';
    case Code1003 = 'evatr-1003';
    case Code1004 = 'evatr-1004';
    case Code2001 = 'evatr-2001';
    case Code2002 = 'evatr-2002';
    case Code2003 = 'evatr-2003';
    case Code2004 = 'evatr-2004';
    case Code2005 = 'evatr-2005';
    case Code2006 = 'evatr-2006';
    case Code2007 = 'evatr-2007';
    case Code2008 = 'evatr-2008';
    case Code2011 = 'evatr-2011';
    case Code3011 = 'evatr-3011';

    public function category(): StatusCategory
    {
        return match ($this) {
            self::Code0000 => StatusCategory::Result,
            self::Code0001 => StatusCategory::Notice,
            self::Code0002 => StatusCategory::Notice,
            self::Code0003 => StatusCategory::Notice,
            self::Code0004 => StatusCategory::Error,
            self::Code0005 => StatusCategory::Error,
            self::Code0006 => StatusCategory::Notice,
            self::Code0007 => StatusCategory::Notice,
            self::Code0008 => StatusCategory::Notice,
            self::Code0011 => StatusCategory::Error,
            self::Code0012 => StatusCategory::Error,
            self::Code0013 => StatusCategory::Error,
            self::Code1001 => StatusCategory::Error,
            self::Code1002 => StatusCategory::Error,
            self::Code1003 => StatusCategory::Error,
            self::Code1004 => StatusCategory::Error,
            self::Code2001 => StatusCategory::Notice,
            self::Code2002 => StatusCategory::Notice,
            self::Code2003 => StatusCategory::Error,
            self::Code2004 => StatusCategory::Error,
            self::Code2005 => StatusCategory::Error,
            self::Code2006 => StatusCategory::Notice,
            self::Code2007 => StatusCategory::Error,
            self::Code2008 => StatusCategory::Notice,
            self::Code2011 => StatusCategory::Error,
            self::Code3011 => StatusCategory::Error,
        };
    }

    /**
     * The BZSt's message in German.
     */
    public function message(): string
    {
        return match ($this) {
            self::Code0000 => 'Die angefragte USt-IdNr. ist zum Anfragezeitpunkt gültig.',
            self::Code0001 => 'Bitte bestätigen Sie den Datenschutzhinweis.',
            self::Code0002 => 'Mindestens eins der Pflichtfelder ist nicht besetzt.',
            self::Code0003 => 'Die angefragte USt-IdNr. ist zum Anfragezeitpunkt gültig. Mindestens eines der Pflichtfelder für eine qualifizierte Bestätigungsanfrage ist nicht besetzt.',
            self::Code0004 => 'Die anfragende DE USt-IdNr. ist syntaktisch falsch. Sie passt nicht in das deutsche Erzeugungsschema.',
            self::Code0005 => 'Die angegebene angefragte USt-IdNr. ist syntaktisch falsch.',
            self::Code0006 => 'Die anfragende DE USt-IdNr. ist nicht berechtigt eine DE USt-IdNr. anzufragen.',
            self::Code0007 => 'Fehlerhafter Aufruf.',
            self::Code0008 => 'Die maximale Anzahl von qualifizierten Bestätigungsabfragen für diese Session wurde erreicht. Bitte starten Sie erneut mit einer einfachen Bestätigungsabfrage.',
            self::Code0011 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
            self::Code0012 => 'Die angefragte USt-IdNr. ist syntaktisch falsch. Sie passt nicht in das Erzeugungsschema.',
            self::Code0013 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
            self::Code1001 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
            self::Code1002 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
            self::Code1003 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
            self::Code1004 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
            self::Code2001 => 'Die angefragte USt-IdNr. ist zum Anfragezeitpunkt nicht vergeben.',
            self::Code2002 => 'Die angefragte USt-IdNr. ist zum Anfragezeitpunkt nicht gültig. Sie ist erst gültig ab dem Datum im Feld gueltigAb.',
            self::Code2003 => 'Das angegebene Länderkennzeichen der angefragten USt-IdNr. ist nicht gültig.',
            self::Code2004 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
            self::Code2005 => 'Die angegebene eigene DE USt-IdNr. ist zum Anfragezeitpunkt nicht gültig.',
            self::Code2006 => 'Die angefragte USt-IdNr. ist zum Anfragezeitpunkt nicht gültig. Sie war gültig im Zeitraum, der durch die Werte in den Feldern gueltigAb und gueltigBis beschrieben ist.',
            self::Code2007 => 'Bei der Verarbeitung der Daten aus dem angefragten EU-Mitgliedstaat ist ein Fehler aufgetreten. Ihre Anfrage kann deshalb nicht bearbeitet werden.',
            self::Code2008 => 'Die angefragte USt-IdNr. ist zum Anfragezeitpunkt gültig. Für die qualifizierte Bestätigungsanfrage liegt einer Besonderheit vor. Für Rückfragen wenden Sie sich an das BZSt.',
            self::Code2011 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
            self::Code3011 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
        };
    }

    /**
     * The request field the status refers to, comma-separated when several; null when none.
     */
    public function field(): ?string
    {
        return match ($this) {
            self::Code0000 => null,
            self::Code0001 => 'datenschutz',
            self::Code0002 => 'angefragteUstid',
            self::Code0003 => 'firmenname,ort',
            self::Code0004 => 'anfragendeUstid',
            self::Code0005 => 'angefragteUstid',
            self::Code0006 => 'anfragendeUstid',
            self::Code0007 => null,
            self::Code0008 => null,
            self::Code0011 => null,
            self::Code0012 => 'angefragteUstid',
            self::Code0013 => null,
            self::Code1001 => null,
            self::Code1002 => null,
            self::Code1003 => null,
            self::Code1004 => null,
            self::Code2001 => 'angefragteUstid',
            self::Code2002 => 'angefragteUstid',
            self::Code2003 => 'angefragteUstid',
            self::Code2004 => null,
            self::Code2005 => 'anfragendeUstid',
            self::Code2006 => 'angefragteUstid',
            self::Code2007 => null,
            self::Code2008 => null,
            self::Code2011 => null,
            self::Code3011 => null,
        };
    }

    /**
     * The HTTP status eVATR answers with; null where the BZSt names none.
     */
    public function httpStatus(): ?int
    {
        return match ($this) {
            self::Code0000 => 200,
            self::Code0001 => null,
            self::Code0002 => 400,
            self::Code0003 => 400,
            self::Code0004 => 400,
            self::Code0005 => 400,
            self::Code0006 => 403,
            self::Code0007 => 403,
            self::Code0008 => 403,
            self::Code0011 => 503,
            self::Code0012 => 400,
            self::Code0013 => 503,
            self::Code1001 => 503,
            self::Code1002 => 500,
            self::Code1003 => 500,
            self::Code1004 => 500,
            self::Code2001 => 404,
            self::Code2002 => 200,
            self::Code2003 => 400,
            self::Code2004 => 500,
            self::Code2005 => 404,
            self::Code2006 => 200,
            self::Code2007 => 500,
            self::Code2008 => 200,
            self::Code2011 => 500,
            self::Code3011 => 500,
        };
    }
}
