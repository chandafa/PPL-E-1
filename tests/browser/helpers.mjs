export async function chooseInvoice(page, invoiceId) {
    await page.getByRole('combobox', { name: 'Invoice D-2', exact: true }).fill(invoiceId);
    await page.getByRole('option').filter({ hasText: invoiceId }).click();
}
