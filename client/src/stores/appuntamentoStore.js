import { defineStore } from 'pinia'
import axios from 'axios'

export const useAppuntamentoStore = defineStore('appuntamento', {
  state: () => ({
    isLoading: false,
    appuntamenti: [],
    appuntamentiFuturi: [],
  }),

  actions: {
    async fetchAppuntamenti(stato = null) {
      this.isLoading = true
      try {
        const params = stato ? { stato } : {}
        const response = await axios.get('/api/appuntamenti', { params })
        this.appuntamenti = response.data
        return { success: true }
      } catch (error) {
        console.error('Errore nel caricamento degli appuntamenti:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nel caricamento degli appuntamenti'
        }
      } finally {
        this.isLoading = false
      }
    },

    async fetchAppuntamentiFuturi() {
      this.isLoading = true
      try {
        const response = await axios.get('/api/appuntamenti-futuri')
        this.appuntamentiFuturi = response.data
        return { success: true }
      } catch (error) {
        console.error('Errore nel caricamento degli appuntamenti futuri:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nel caricamento degli appuntamenti futuri'
        }
      } finally {
        this.isLoading = false
      }
    },

    async aggiornaStatoAppuntamento(id, stato, note = null) {
      this.isLoading = true
      try {
        const response = await axios.put(`/api/appuntamenti/${id}/stato`, { stato, note })
        await this.fetchAppuntamenti()
        return {
          success: true,
          message: 'Appuntamento aggiornato con successo',
          data: response.data.data
        }
      } catch (error) {
        console.error('Errore nell\'aggiornamento dell\'appuntamento:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nell\'aggiornamento dell\'appuntamento'
        }
      } finally {
        this.isLoading = false
      }
    },
  },
})
