import { defineStore } from 'pinia'
import axios from 'axios'
import { useAuthStore } from './authStore'

export const usePazienteStore = defineStore('paziente', {
  state: () => ({
    isLoading: false,
    unreadNotificationsCount: 0,
    proposteNuove: [],
    proposteArchiviate: [],
  }),
  actions: {
    /**
     * Carica un nuovo preventivo, inclusi i dati anagrafici se forniti.
     * @param {Object} data - L'oggetto contenente il file e i campi del form.
     */
    async uploadQuote(data) {
      this.isLoading = true

      const formData = new FormData()
      formData.append('preventivo', data.preventivoFile)

      if (data.cellulare) formData.append('cellulare', data.cellulare)
      if (data.indirizzo) formData.append('indirizzo', data.indirizzo)
      if (data.citta) formData.append('citta', data.citta)
      if (data.cap) formData.append('cap', data.cap)
      if (data.provincia) formData.append('provincia', data.provincia)

      try {
        const response = await axios.post('/api/preventivi', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        })

        const authStore = useAuthStore()
        authStore.isAuthCheckCompleted = false
        await authStore.getUser()

        return { success: true, message: response.data.message }
      } catch (error) {
        const message =
          error.response?.data?.message || 'Si è verificato un errore durante il caricamento.'
        return { success: false, message: message }
      } finally {
        this.isLoading = false
      }
    },
    async updateAnagrafica(data) {
      this.isLoading = true
      try {
        const response = await axios.post('/api/impostazioni/anagrafica', data)
        const authStore = useAuthStore()
        await authStore.getUser()
        return { success: true, message: response.data.message }
      } catch (error) {
        return { success: false, message: error.response?.data?.message || 'Errore' }
      } finally {
        this.isLoading = false
      }
    },

    async checkForNotifications() {
      try {
        const response = await axios.get(`/api/notifiche?_=${Date.now()}`)
        this.unreadNotificationsCount = response.data.length
      } catch (error) {
        console.error('Errore nel controllo delle notifiche:', error)
        this.unreadNotificationsCount = 0
      }
    },

    /**
     * Carica le proposte dal backend e le divide in 'nuove' e 'archiviate'.
     */
    async fetchProposte() {
      this.isLoading = true
      try {
        const response = await axios.get('/api/proposte')
        this.proposteNuove = response.data.nuove || []
        this.proposteArchiviate = response.data.archiviate || []
        return { success: true }
      } catch (error) {
        console.error('Errore nel caricamento delle proposte:', error)
        return { success: false, message: 'Errore nel caricamento delle proposte.' }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Segna le proposte nuove come lette e azzera le notifiche.
     */
    async markProposteComeLette() {
      if (this.proposteNuove.length === 0) {
        return { success: true }
      }

      const proposteIds = this.proposteNuove.map((p) => p.id)

      try {
        await axios.post('/api/proposte/mark-as-read-paziente', { proposteIds })
        this.unreadNotificationsCount = 0
        await this.fetchProposte()
        return { success: true }
      } catch (error) {
        console.error('Errore nel segnare le proposte come lette:', error)
        return { success: false, message: "Errore nell'aggiornamento dello stato delle proposte." }
      }
    },

    /**
     * Accetta una proposta.
     * @param {number} propostaId L'ID della proposta da accettare.
     */
    async accettaProposta(propostaId) {
      this.isLoading = true
      try {
        const response = await axios.post(`/api/proposte/${propostaId}/accetta`)
        await this.fetchProposte() // Ricarica per aggiornare lo stato
        return { success: true, message: response.data.message }
      } catch (error) {
        return { success: false, message: "Errore durante l'accettazione della proposta." }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Rifiuta una proposta.
     * @param {number} propostaId L'ID della proposta da rifiutare.
     */
    async rifiutaProposta(propostaId) {
      this.isLoading = true
      try {
        const response = await axios.post(`/api/proposte/${propostaId}/rifiuta`)
        await this.fetchProposte() // Ricarica per aggiornare lo stato
        return { success: true, message: response.data.message }
      } catch (error) {
        return { success: false, message: 'Errore durante il rifiuto della proposta.' }
      } finally {
        this.isLoading = false
      }
    },
  },
})
