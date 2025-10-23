import { defineStore } from 'pinia'
import axios from 'axios'

export const useSalesStore = defineStore('sales', {
  state: () => ({
    isLoading: false,
    proposte: [],
    agendaMedico: [],
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

    async getAgendaMedico(medicoId, dataInizio, dataFine) {
      this.isLoading = true
      try {
        const response = await axios.get(`/api/sales/agenda-medico/${medicoId}`, {
          params: { data_inizio: dataInizio, data_fine: dataFine }
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

    async fissaAppuntamento(slotAppuntamentoId, propostaId, note = null) {
      this.isLoading = true
      try {
        const response = await axios.post('/api/sales/fissa-appuntamento', {
          slot_appuntamento_id: slotAppuntamentoId,
          proposta_id: propostaId,
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
          message: error.response?.data?.message || 'Errore nel fissare l\'appuntamento'
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
      this.agendaMedico = []
    },
  },
})
