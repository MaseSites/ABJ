// Neutrales Zahlungs-Interface.
//
// Aktuell: manuelle Abwicklung ohne externen Zahlungsanbieter.
// Das hält den Checkout schlank und vermeidet unnötige Abhängigkeiten.

export const paymentProvider = {
  name: 'manual',

  /**
   * Startet den Bezahlvorgang für eine Bestellung.
   * @returns {{ redirectUrl: string|null, provider: string }}
   */
  async createCheckout(order) {
    // Keine externe Zahlung – Bestellung gilt als "offen" und wird manuell bearbeitet.
    return { redirectUrl: null, provider: 'manual' };
  },

  /**
   * Bestätigt eine Zahlung (z.B. via Webhook). Aktuell No-Op.
   */
  async confirmPayment(/* reference, payload */) {
    return { confirmed: false };
  },
};
