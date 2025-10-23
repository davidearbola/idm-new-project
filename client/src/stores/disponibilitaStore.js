import { defineStore } from 'pinia'
import axios from 'axios'

export const useDisponibilitaStore = defineStore('disponibilita', {
  state: () => ({
    isLoading: false,
    disponibilita: [],
    giorniSettimana: [
      { value: 1, label: 'Lunedì' },
      { value: 2, label: 'Martedì' },
      { value: 3, label: 'Mercoledì' },
      { value: 4, label: 'Giovedì' },
      { value: 5, label: 'Venerdì' },
      { value: 6, label: 'Sabato' },
      { value: 7, label: 'Domenica' },
    ],
  }),

  getters: {
    disponibilitaPerGiorno: (state) => {
      const grouped = {}
      state.disponibilita.forEach(disp => {
        if (!grouped[disp.giorno_settimana]) {
          grouped[disp.giorno_settimana] = []
        }
        grouped[disp.giorno_settimana].push(disp)
      })
      return grouped
    },

    getNomeGiorno: (state) => (numero) => {
      const giorno = state.giorniSettimana.find(g => g.value === numero)
      return giorno ? giorno.label : ''
    },
  },

  actions: {
    async fetchDisponibilita() {
      this.isLoading = true
      try {
        const response = await axios.get('/api/disponibilita')
        this.disponibilita = response.data
        return { success: true }
      } catch (error) {
        console.error('Errore nel caricamento delle disponibilità:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nel caricamento delle disponibilità'
        }
      } finally {
        this.isLoading = false
      }
    },

    async creaDisponibilita(data) {
      this.isLoading = true
      try {
        const response = await axios.post('/api/disponibilita', data)
        await this.fetchDisponibilita()
        return {
          success: true,
          message: 'Disponibilità creata con successo',
          data: response.data.data
        }
      } catch (error) {
        console.error('Errore nella creazione della disponibilità:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nella creazione della disponibilità',
          errors: error.response?.data?.errors
        }
      } finally {
        this.isLoading = false
      }
    },

    async aggiornaDisponibilita(id, data) {
      this.isLoading = true
      try {
        const response = await axios.put(`/api/disponibilita/${id}`, data)
        await this.fetchDisponibilita()
        return {
          success: true,
          message: 'Disponibilità aggiornata con successo',
          data: response.data.data
        }
      } catch (error) {
        console.error('Errore nell\'aggiornamento della disponibilità:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nell\'aggiornamento della disponibilità',
          errors: error.response?.data?.errors
        }
      } finally {
        this.isLoading = false
      }
    },

    async eliminaDisponibilita(id) {
      this.isLoading = true
      try {
        await axios.delete(`/api/disponibilita/${id}`)
        await this.fetchDisponibilita()
        return {
          success: true,
          message: 'Disponibilità eliminata con successo'
        }
      } catch (error) {
        console.error('Errore nell\'eliminazione della disponibilità:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nell\'eliminazione della disponibilità'
        }
      } finally {
        this.isLoading = false
      }
    },

    async rigeneraSlots() {
      this.isLoading = true
      try {
        await axios.post('/api/disponibilita/rigenera-slots')
        return {
          success: true,
          message: 'Slots rigenerati con successo'
        }
      } catch (error) {
        console.error('Errore nella rigenerazione degli slots:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nella rigenerazione degli slots'
        }
      } finally {
        this.isLoading = false
      }
    },
  },
})
