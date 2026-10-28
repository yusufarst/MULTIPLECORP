// The document types' names in the interface (LOCALIZATION §6.5): `documents.doc_01` … `documents.doc_14`.
import type { TranslationKey } from '@/lib/i18n';

export const typeName = (type: string): TranslationKey => `documents.${type.toLowerCase().replace('-', '_')}` as TranslationKey;
