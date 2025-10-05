import { defineStore } from 'pinia'
import axios from 'axios'

export const usePreventivoStore = defineStore('preventivo', {
  state: () => ({
    preventivoId: null,
    statoElaborazione: null,
    vociPreventivo: null,
    messaggioErrore: null,
    proposteDisponibili: false,
    proposte: [],
    isLoading: false,
    uploadProgress: 0,
  }),

  getters: {
    isPreventivoCaricato: (state) => !!state.preventivoId,
    isElaborazioneCompletata: (state) => state.statoElaborazione === 'completato',
    isInAttesaDatiPaziente: (state) => state.statoElaborazione === 'attesa_dati_paziente',
    isRicercaProposte: (state) => state.statoElaborazione === 'ricerca_proposte',
    isPropostePronte: (state) => state.statoElaborazione === 'proposte_pronte',
    haErrore: (state) => state.statoElaborazione === 'errore',
    isSenzaProposte: (state) => state.statoElaborazione === 'senza_proposte',
  },

  actions: {
    /**
     * Carica un preventivo (file PDF/immagine)
     */
    async caricaPreventivo(file) {
      this.isLoading = true
      this.uploadProgress = 0

      try {
        const formData = new FormData()
        formData.append('preventivo', file)

        const response = await axios.post('/api/preventivi', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
          onUploadProgress: (progressEvent) => {
            this.uploadProgress = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            )
          },
        })

        if (response.data.success) {
          this.preventivoId = response.data.preventivo_id
          this.statoElaborazione = 'caricato'
          return { success: true, message: response.data.message }
        }

        return { success: false, message: 'Errore durante il caricamento' }
      } catch (error) {
        const errorMessage =
          error.response?.data?.message ||
          'Errore durante il caricamento del preventivo'
        return { success: false, message: errorMessage }
      } finally {
        this.isLoading = false
        this.uploadProgress = 0
      }
    },

    /**
     * Controlla lo stato di elaborazione del preventivo
     */
    async controllaStato() {
      if (!this.preventivoId) return

      try {
        const response = await axios.get(`/api/preventivi/${this.preventivoId}/stato`)

        this.statoElaborazione = response.data.stato_elaborazione
        this.vociPreventivo = response.data.voci_preventivo
        this.messaggioErrore = response.data.messaggio_errore

        return {
          success: true,
          stato: response.data.stato_elaborazione,
          voci: response.data.voci_preventivo,
        }
      } catch (error) {
        console.error('Errore nel controllo dello stato:', error)
        return { success: false, message: 'Errore nel controllo dello stato' }
      }
    },

    /**
     * Conferma le voci del preventivo (modificate o meno)
     */
    async confermaVoci(voci) {
      if (!this.preventivoId) return { success: false, message: 'Nessun preventivo caricato' }

      this.isLoading = true

      try {
        const response = await axios.post(
          `/api/preventivi/${this.preventivoId}/conferma`,
          { voci }
        )

        if (response.data.success) {
          this.statoElaborazione = 'attesa_dati_paziente'
          // Aggiorna le voci nello store con quelle confermate
          this.vociPreventivo = voci
          return { success: true, message: response.data.message }
        }

        return { success: false, message: 'Errore durante la conferma' }
      } catch (error) {
        const errorMessage =
          error.response?.data?.error || 'Errore durante la conferma delle voci'
        return { success: false, message: errorMessage }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Salva i dati del paziente e avvia la ricerca delle proposte
     */
    async salvaDatiPaziente(datiPaziente) {
      if (!this.preventivoId) return { success: false, message: 'Nessun preventivo caricato' }

      this.isLoading = true

      try {
        const response = await axios.post(
          `/api/preventivi/${this.preventivoId}/salva-dati-paziente`,
          datiPaziente
        )

        if (response.data.success) {
          this.statoElaborazione = 'ricerca_proposte'
          return { success: true, message: response.data.message }
        }

        return { success: false, message: 'Errore durante il salvataggio' }
      } catch (error) {
        const errorMessage =
          error.response?.data?.message || 'Errore durante il salvataggio dei dati'

        // Gestione errori di validazione
        if (error.response?.status === 422) {
          const errors = error.response.data.errors
          const firstError = Object.values(errors)[0][0]
          return { success: false, message: firstError }
        }

        return { success: false, message: errorMessage }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Controlla se sono pronte le proposte
     */
    async controllaProposte() {
      if (!this.preventivoId) return

      try {
        const response = await axios.get(
          `/api/preventivi/${this.preventivoId}/proposte-stato`
        )

        this.statoElaborazione = response.data.stato_elaborazione
        this.proposte = response.data.proposte_pronte || []
        this.proposteDisponibili = this.proposte.length > 0

        // Aggiorna anche le voci del preventivo con quelle confermate dal paziente
        if (response.data.voci_preventivo) {
          this.vociPreventivo = response.data.voci_preventivo
        }

        return {
          success: true,
          proposte_pronte: response.data.proposte_pronte,
          stato: response.data.stato_elaborazione,
        }
      } catch (error) {
        console.error('Errore nel controllo delle proposte:', error)
        return { success: false, message: 'Errore nel controllo delle proposte' }
      }
    },

    /**
     * Reset dello store
     */
    reset() {
      this.preventivoId = null
      this.statoElaborazione = null
      this.vociPreventivo = null
      this.messaggioErrore = null
      this.proposteDisponibili = false
      this.proposte = []
      this.isLoading = false
      this.uploadProgress = 0
    },
  },
})
