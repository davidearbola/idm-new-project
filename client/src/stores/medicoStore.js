import { defineStore } from 'pinia'
import axios from 'axios'
import { useAuthStore } from './authStore'

export const useMedicoStore = defineStore('medico', {
  state: () => ({ isLoading: false, listino: [] }),
  actions: {
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
    // Carica il listino completo del medico
    async fetchListino() {
      this.isLoading = true
      try {
        const response = await axios.get('/api/listino')
        this.listino = response.data
        return { success: true }
      } catch (error) {
        this.listino = []
        return { success: false, message: 'Errore nel caricamento del listino.' }
      } finally {
        this.isLoading = false
      }
    },
    // Salva tutte le modifiche in blocco
    async saveListino(payload) {
      this.isLoading = true
      const { masterItems, customItems } = payload
      try {
        const requests = []
        if (masterItems.length > 0) {
          requests.push(axios.post('/api/listino/master', { items: masterItems }))
        }
        if (customItems.length > 0) {
          requests.push(axios.post('/api/listino/custom', { items: customItems }))
        }
        if (requests.length === 0) {
          return { success: true, message: 'Nessuna modifica da salvare.' }
        }
        await Promise.all(requests)
        await this.fetchListino()
        return { success: true, message: 'Listino salvato con successo!' }
      } catch (error) {
        return {
          success: false,
          message: error.response?.data?.message || 'Errore durante il salvataggio.',
        }
      } finally {
        this.isLoading = false
      }
    },

    async updateCustomItem(item) {
      this.isLoading = true
      try {
        const response = await axios.put(`/api/listino/custom/${item.id}`, item)
        await this.fetchListino() // Ricarica per consistenza
        return { success: true, message: response.data.message }
      } catch (error) {
        return {
          success: false,
          message: error.response?.data?.message || "Errore durante l'aggiornamento.",
        }
      } finally {
        this.isLoading = false
      }
    },
    async deleteCustomItem(itemId) {
      this.isLoading = true
      try {
        const response = await axios.delete(`/api/listino/custom/${itemId}`)
        const index = this.listino.findIndex((item) => item.id === itemId)
        if (index !== -1) this.listino.splice(index, 1)

        return { success: true, message: response.data.message }
      } catch (error) {
        return {
          success: false,
          message: error.response?.data?.message || "Errore durante l'eliminazione.",
        }
      } finally {
        this.isLoading = false
      }
    },
  },
})
