<?php

namespace App\Modules\ORG\Enums;

/**
 * The numbered sequence types of DATABASE §4.2 (`numbering_schemes`, `number_sequences`; D-DB-12): the fourteen
 * document types, the project number and the purchase number (`DIR-077` CA-09), each numbered per company and
 * period (C-10; BR-DOC-02). Its address segment on PG-061 is its lower-case code (DIR-098 reading 3), its audit
 * code suffix the code with an underscore (DIR-098 reading 5), and a seed row of the opening import carries the
 * registered `import_kind` below (NM-03).
 */
enum SequenceType: string
{
    case Doc01 = 'DOC-01';
    case Doc02 = 'DOC-02';
    case Doc03 = 'DOC-03';
    case Doc04 = 'DOC-04';
    case Doc05 = 'DOC-05';
    case Doc06 = 'DOC-06';
    case Doc07 = 'DOC-07';
    case Doc08 = 'DOC-08';
    case Doc09 = 'DOC-09';
    case Doc10 = 'DOC-10';
    case Doc11 = 'DOC-11';
    case Doc12 = 'DOC-12';
    case Doc13 = 'DOC-13';
    case Doc14 = 'DOC-14';
    case Project = 'PROJECT';
    case Purchase = 'PURCHASE';

    /** The PG-061 address segment: `doc-01`, `project`, `purchase`. */
    public function slug(): string
    {
        return strtolower($this->value);
    }

    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom(strtoupper($slug));
    }

    /** The audit-code suffix: `DOC_01`, `PROJECT`, `PURCHASE` — an action code holds no hyphen. */
    public function auditSuffix(): string
    {
        return str_replace('-', '_', $this->value);
    }

    /**
     * The registered `import_kind` of the opening import's seed row for this type (NM-03): the seed boundary reads
     * the batches holding a COMMITTED row of it; the row's `import_identity` is the period it seeds.
     */
    public function seedImportKind(): string
    {
        return 'NUMBER_SEED:'.$this->value;
    }

    /** The catalogue key of the type's name. */
    public function labelKey(): string
    {
        return match ($this) {
            self::Project => 'glossary.gl_004',
            self::Purchase => 'glossary.gl_006',
            default => 'documents.'.strtolower(str_replace('-', '_', $this->value)),
        };
    }
}
