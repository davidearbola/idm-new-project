import { defineStore } from 'pinia'
import axios from '@/axios'

export const useTelmarStore = defineStore('telmar', {
  state: () => ({
    isLoading: false,
  }),
  actions: {
    /**
     * Richiedi una chiamata dall'operatore per una proposta specifica
     * @param {Object} payload - { proposta_id, nome, cognome, cellulare }
     * @returns {Promise<Object>} - { success, message }
     */
    async richiediChiamata(payload) {
      this.isLoading = true
      try {
        const response = await axios.post('/api/preventivi/richiedi-chiamata', payload)
        return {
          success: true,
          message: response.data.message || 'Richiesta di chiamata inviata con successo!',
        }
      } catch (error) {
        if (error.response && error.response.data) {
          return {
            success: false,
            message: error.response.data.message || 'Errore durante l\'invio della richiesta',
          }
        }
        return {
          success: false,
          message: 'Si è verificato un errore. Riprova più tardi.',
        }
      } finally {
        this.isLoading = false
      }
    },
  },
})
