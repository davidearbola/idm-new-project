import { defineStore } from 'pinia'
import axios from 'axios'

export const usePoltronaStore = defineStore('poltrona', {
  state: () => ({
    isLoading: false,
    poltrone: [],
  }),

  actions: {
    async fetchPoltrone() {
      this.isLoading = true
      try {
        const response = await axios.get('/api/poltrone')
        this.poltrone = response.data
        return { success: true }
      } catch (error) {
        console.error('Errore nel caricamento delle poltrone:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nel caricamento delle poltrone'
        }
      } finally {
        this.isLoading = false
      }
    },

    async creaPoltrona(data) {
      this.isLoading = true
      try {
        const response = await axios.post('/api/poltrone', data)
        await this.fetchPoltrone()
        return {
          success: true,
          message: 'Poltrona creata con successo',
          data: response.data.data
        }
      } catch (error) {
        console.error('Errore nella creazione della poltrona:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nella creazione della poltrona',
          errors: error.response?.data?.errors
        }
      } finally {
        this.isLoading = false
      }
    },

    async aggiornaPoltrona(id, data) {
      this.isLoading = true
      try {
        const response = await axios.put(`/api/poltrone/${id}`, data)
        await this.fetchPoltrone()
        return {
          success: true,
          message: 'Poltrona aggiornata con successo',
          data: response.data.data
        }
      } catch (error) {
        console.error('Errore nell\'aggiornamento della poltrona:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nell\'aggiornamento della poltrona',
          errors: error.response?.data?.errors
        }
      } finally {
        this.isLoading = false
      }
    },

    async eliminaPoltrona(id) {
      this.isLoading = true
      try {
        await axios.delete(`/api/poltrone/${id}`)
        await this.fetchPoltrone()
        return {
          success: true,
          message: 'Poltrona eliminata con successo'
        }
      } catch (error) {
        console.error('Errore nell\'eliminazione della poltrona:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nell\'eliminazione della poltrona'
        }
      } finally {
        this.isLoading = false
      }
    },
  },
})
