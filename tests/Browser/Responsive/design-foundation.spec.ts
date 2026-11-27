import { expect, type Page, test } from '@playwright/test';

// TL-09 and TL-11 (TASK-098; CR-002 K-5, D-8 #6; DESIGN_SYSTEM §8, §9.4, §10 "States of a region"): the design foundation's
// behaviours on Chromium and WebKit — a region that loads behind a skeleton only after 300 ms, its status line there from the
// first render and its one word said once, the shapes hidden from assistive technology and still under reduced motion, and a
// link drawn as a button that stays a plain link.

/** Presses the specimen's button and reads the region at the next frame and when its first shape arrives. */
async function load(page: Page): Promise<{ early: { role: string | null; text: string; shapes: number }; elapsed: number }> {
    return page.evaluate(
        () =>
            new Promise((resolve) => {
                const specimen = document.querySelector('[data-specimen="loading"]');
                const button = specimen?.querySelector('button');
                const started = performance.now();
                const shapes = () => specimen?.querySelectorAll('[data-region-state="loading"] [data-slot="skeleton"]').length ?? 0;
                let early = { role: null as string | null, text: '', shapes: -1 };
                const observer = new MutationObserver(() => {
                    if (shapes() > 0) {
                        observer.disconnect();
                        resolve({ early, elapsed: performance.now() - started });
                    }
                });

                observer.observe(document.body, { childList: true, subtree: true });
                button?.click();
                requestAnimationFrame(() => {
                    const region = specimen?.querySelector('[data-region-state="loading"]') ?? null;
                    early = { role: region?.getAttribute('role') ?? null, text: region?.textContent ?? '', shapes: shapes() };
                });
            }),
    );
}

test('loads behind a skeleton only after 300 ms, its status line there from the first render (K-5; criterion 1)', async ({ page }) => {
    await page.goto('/__fixtures/komponen?bahasa=id');
    const result = await load(page);

    // At the next frame: the live region exists and is empty, no shape, no word.
    expect(result.early).toEqual({ role: 'status', text: '', shapes: 0 });
    // The shapes come with the spinner's own 300 ms delay, not before.
    expect(result.elapsed).toBeGreaterThanOrEqual(290);
    expect(result.elapsed).toBeLessThan(2000);

    const region = page.locator('[data-specimen="loading"] [data-region-state="loading"]');
    const shapes = region.locator('[data-slot="skeleton"]');

    await expect(shapes).toHaveCount(3);
    await expect(region).toHaveAttribute('role', 'status');
    // The one word is said once, by the region; the shapes are hidden from assistive technology.
    await expect(region).toHaveText('Memuat');
    for (const shape of await shapes.all()) {
        await expect(shape).toHaveAttribute('aria-hidden', 'true');
    }
});

test('keeps its shapes still under reduced motion, and pulses them otherwise (K-5; D-DS-17)', async ({ page }) => {
    const motion = () => page.locator('[data-specimen="loading"] [data-slot="skeleton"]').first().evaluate((shape) => getComputedStyle(shape).animationName);

    await page.goto('/__fixtures/komponen?bahasa=id');
    await load(page);
    await expect.poll(motion).toBe('pulse');

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect.poll(motion).toBe('none');
});

test('draws a link as a button without typing it as one, and keeps a real button\'s type (D-8 #6)', async ({ page }) => {
    await page.goto('/__fixtures/komponen?bahasa=id');
    const specimen = page.locator('[data-specimen="link-button"]');
    const link = specimen.locator('a');

    await expect(link).toHaveAttribute('href', '/__fixtures/komponen');
    expect(await link.getAttribute('type')).toBeNull();
    await expect(link).toHaveAttribute('data-slot', 'button');
    await expect(specimen.locator('button')).toHaveAttribute('type', 'button');
});
