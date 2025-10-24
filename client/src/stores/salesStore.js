import { defineStore } from 'pinia'
import axios from 'axios'

export const useSalesStore = defineStore('sales', {
  state: () => ({
    isLoading: false,
    proposte: [],
    agendaMedico: null, // Struttura: { poltrone: [], periodo: {} }
    propostaSelezionata: null,
  }),

  actions: {
    async cercaProposte(search) {
      this.isLoading = true
      try {
        const response = await axios.post('/api/sales/cerca-proposte', { search })
        this.proposte = response.data
        return { success: true, data: response.data }
      } catch (error) {
        console.error('Errore nella ricerca delle proposte:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nella ricerca delle proposte'
        }
      } finally {
        this.isLoading = false
      }
    },

    async getAgendaMedico(medicoId, dataInizio) {
      this.isLoading = true
      try {
        const response = await axios.get(`/api/sales/agenda-medico/${medicoId}`, {
          params: { data_inizio: dataInizio }
        })
        this.agendaMedico = response.data
        return { success: true, data: response.data }
      } catch (error) {
        console.error('Errore nel caricamento dell\'agenda:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nel caricamento dell\'agenda'
        }
      } finally {
        this.isLoading = false
      }
    },

    async fissaAppuntamento(propostaId, poltronaId, startingDateTime, endingDateTime, note = null) {
      this.isLoading = true
      try {
        const response = await axios.post('/api/sales/fissa-appuntamento', {
          proposta_id: propostaId,
          poltrona_id: poltronaId,
          starting_date_time: startingDateTime,
          ending_date_time: endingDateTime,
          note
        })
        return {
          success: true,
          message: 'Appuntamento fissato con successo',
          data: response.data.data
        }
      } catch (error) {
        console.error('Errore nel fissare l\'appuntamento:', error)
        return {
          success: false,
          message: error.response?.data?.message || 'Errore nel fissare l\'appuntamento',
          errors: error.response?.data?.errors
        }
      } finally {
        this.isLoading = false
      }
    },

    setPropostaSelezionata(proposta) {
      this.propostaSelezionata = proposta
    },

    resetPropostaSelezionata() {
      this.propostaSelezionata = null
      this.agendaMedico = null
    },
  },
})
