import { randomBytes } from 'node:crypto';
import { crc32, deflateSync } from 'node:zlib';

import { type Locator, type Page } from '@playwright/test';

// PG-038's browser fixtures (TASK-030): a synthetic company for the run — with a reference-less client order for
// IP-05's warning when asked (EvidenceFixture) —, and files whose bytes no other run repeats, so a content match is
// only ever one the journey makes (FL-10): a minimal PDF and a small PNG standing in for a phone's photo.

export type EvidenceCompany = { company: string; code: string; issuer: string };

export async function evidenceCompany(page: Page, duplicate = false): Promise<EvidenceCompany> {
    return (await (await page.request.post('/__fixtures/bukti', { data: { duplicate } })).json()) as EvidenceCompany;
}

export function uniquePdf(): Buffer {
    return Buffer.from(`%PDF-1.4\n%${randomBytes(8).toString('hex')}\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\nstartxref\n9\n%%EOF\n`);
}

function chunk(type: string, data: Buffer): Buffer {
    const length = Buffer.alloc(4);
    length.writeUInt32BE(data.length);
    const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(body));

    return Buffer.concat([length, body, crc]);
}

/** A 4×4 RGB PNG of one random colour. */
export function uniquePhoto(): Buffer {
    const header = Buffer.alloc(13);
    header.writeUInt32BE(4, 0);
    header.writeUInt32BE(4, 4);
    header.writeUInt8(8, 8);
    header.writeUInt8(2, 9);
    const colour = randomBytes(3);
    const row = Buffer.concat([Buffer.from([0]), colour, colour, colour, colour]);

    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        chunk('IHDR', header),
        chunk('IDAT', deflateSync(Buffer.concat([row, row, row, row]))),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}

/** Drops a file on the upload field's area, as a desktop's drag and drop does (J-DOC-02). */
export async function dropFile(page: Page, zone: Locator, name: string, type: string, bytes: Buffer): Promise<void> {
    const transfer = await page.evaluateHandle(
        ({ data, fileName, mime }) => {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(new File([new Uint8Array(data)], fileName, { type: mime }));

            return dataTransfer;
        },
        { data: [...bytes], fileName: name, mime: type },
    );

    await zone.dispatchEvent('dragover', { dataTransfer: transfer });
    await zone.dispatchEvent('drop', { dataTransfer: transfer });
}
