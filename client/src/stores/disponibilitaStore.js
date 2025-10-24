import { defineStore } from 'pinia'
import axios from 'axios'

export const useDisponibilitaStore = defineStore('disponibilita', {
  state: () => ({
    isLoading: false,
    poltrone: [], // Array di poltrone con le loro disponibilità nested
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
    // Ritorna le disponibilità raggruppate per poltrona e giorno
    disponibilitaPerPoltronaEGiorno: (state) => {
      const grouped = {}
      state.poltrone.forEach(poltrona => {
        grouped[poltrona.id] = {}
        poltrona.disponibilita.forEach(disp => {
          if (!grouped[poltrona.id][disp.giorno_settimana]) {
            grouped[poltrona.id][disp.giorno_settimana] = []
          }
          grouped[poltrona.id][disp.giorno_settimana].push(disp)
        })
      })
      return grouped
    },

    getNomeGiorno: (state) => (numero) => {
      const giorno = state.giorniSettimana.find(g => g.value === numero)
      return giorno ? giorno.label : ''
    },

    // Ritorna tutte le disponibilità piatte (per compatibilità)
    tutteLeDisponibilita: (state) => {
      const tutte = []
      state.poltrone.forEach(poltrona => {
        poltrona.disponibilita.forEach(disp => {
          tutte.push({
            ...disp,
            poltrona_id: poltrona.id,
            nome_poltrona: poltrona.nome_poltrona
          })
        })
      })
      return tutte
    },
  },

  actions: {
    async fetchDisponibilita() {
      this.isLoading = true
      try {
        const response = await axios.get('/api/disponibilita')
        // La risposta ora contiene le poltrone con le disponibilità nested
        this.poltrone = response.data
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
  },
})
