<template>
  <div class="container-fluid py-4">
    <div class="row mb-4">
      <div class="col">
        <h2>I miei Appuntamenti</h2>
        <p class="text-muted">Gestisci tutti i tuoi appuntamenti</p>
      </div>
    </div>

    <!-- Filtri -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Filtra per Stato</label>
            <select v-model="filtroStato" class="form-select" @change="applicaFiltri">
              <option value="">Tutti gli stati</option>
              <option value="nuovo">Nuovi</option>
              <option value="visualizzato">Visualizzati</option>
              <option value="assente">Assente</option>
              <option value="cancellato">Cancellati</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Cerca Paziente (Email o Cellulare)</label>
            <input
              v-model="cercaPaziente"
              type="text"
              class="form-control"
              placeholder="Inserisci email o cellulare..."
              @input="applicaFiltri"
            >
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-outline-secondary w-100" @click="resetFiltri">
              <i class="fas fa-redo me-2"></i>Reset Filtri
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Caricamento...</span>
      </div>
    </div>

    <div v-else>
      <div class="card shadow-sm">
        <div class="card-body">
          <div v-if="appuntamentiFiltrati.length === 0" class="text-center py-5">
            <i class="fas fa-calendar-check display-1 text-muted"></i>
            <p class="mt-3 text-muted">Nessun appuntamento trovato</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Data e Ora</th>
                  <th>Paziente</th>
                  <th>Contatto</th>
                  <th>Note</th>
                  <th>Stato</th>
                  <th>Azioni</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="app in appuntamentiFiltrati" :key="app.id">
                  <td>
                    <span class="badge bg-secondary">#{{ app.id }}</span>
                  </td>
                  <td>
                    <div class="fw-bold">
                      {{ formatDate(app.starting_date_time) }}
                    </div>
                    <small class="text-muted">
                      {{ formatTime(app.starting_date_time) }} -
                      {{ formatTime(app.ending_date_time) }}
                    </small>
                  </td>
                  <td>
                    <div class="fw-bold">
                      {{ app.proposta?.preventivo_paziente?.nome_paziente }} {{ app.proposta?.preventivo_paziente?.cognome_paziente || 'N/A' }}
                    </div>
                  </td>
                  <td>
                    <div class="small">
                      <i class="fas fa-envelope me-1"></i>
                      {{ app.proposta?.preventivo_paziente?.email_paziente || 'N/A' }}
                    </div>
                    <div class="small" v-if="app.proposta?.preventivo_paziente?.cellulare_paziente">
                      <i class="fas fa-phone me-1"></i>
                      {{ app.proposta.preventivo_paziente.cellulare_paziente }}
                    </div>
                  </td>
                  <td>
                    <small>{{ app.note || '-' }}</small>
                  </td>
                  <td>
                    <span :class="getStatoBadgeClass(app.stato)">
                      {{ getStatoLabel(app.stato) }}
                    </span>
                  </td>
                  <td>
                    <div class="btn-group" role="group">
                      <button
                        v-if="['nuovo', 'visualizzato'].includes(app.stato)"
                        class="btn btn-sm btn-warning"
                        @click="cambiaStato(app, 'assente')"
                        title="Segna come assente"
                      >
                        <i class="fas fa-user-times"></i>
                      </button>
                      <button
                        v-if="['nuovo', 'visualizzato'].includes(app.stato)"
                        class="btn btn-sm btn-danger"
                        @click="cambiaStato(app, 'cancellato')"
                        title="Cancella"
                      >
                        <i class="fas fa-times-circle"></i>
                      </button>
                      <button
                        class="btn btn-sm btn-outline-primary"
                        @click="mostraDettagli(app)"
                        title="Dettagli"
                      >
                        <i class="fas fa-info-circle"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Dettagli -->
    <div class="modal fade" ref="modalDettagli" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Dettagli Appuntamento</h5>
            <button type="button" class="btn-close" @click="chiudiModal"></button>
          </div>
          <div class="modal-body" v-if="appuntamentoSelezionato">
            <div class="row mb-3">
              <div class="col-md-6">
                <h6>Data e Ora</h6>
                <p>
                  {{ formatDate(appuntamentoSelezionato.starting_date_time) }}<br>
                  {{ formatTime(appuntamentoSelezionato.starting_date_time) }} -
                  {{ formatTime(appuntamentoSelezionato.ending_date_time) }}
                </p>
              </div>
              <div class="col-md-6">
                <h6>Stato</h6>
                <p>
                  <span :class="getStatoBadgeClass(appuntamentoSelezionato.stato)">
                    {{ getStatoLabel(appuntamentoSelezionato.stato) }}
                  </span>
                </p>
              </div>
            </div>

            <div class="mb-3">
              <h6>Paziente</h6>
              <p>
                <strong>Nome:</strong>
                {{ appuntamentoSelezionato.proposta?.preventivo_paziente?.nome_paziente }} {{ appuntamentoSelezionato.proposta?.preventivo_paziente?.cognome_paziente || 'N/A' }}<br>
                <strong>Email:</strong>
                {{ appuntamentoSelezionato.proposta?.preventivo_paziente?.email_paziente || 'N/A' }}<br>
                <strong>Cellulare:</strong>
                {{ appuntamentoSelezionato.proposta?.preventivo_paziente?.cellulare_paziente || 'N/A' }}<br>
                <strong>Indirizzo:</strong>
                {{ appuntamentoSelezionato.proposta?.preventivo_paziente?.indirizzo_paziente || 'N/A' }}
              </p>
            </div>

            <div class="mb-3">
              <h6>Voci della Proposta</h6>
              <div v-if="getVociProposta(appuntamentoSelezionato).length > 0">
                <table class="table table-bordered">
                  <thead class="table-light">
                    <tr>
                      <th>Prestazione</th>
                      <th class="text-center" style="width: 100px;">Quantità</th>
                      <th class="text-end" style="width: 120px;">Prezzo Unit.</th>
                      <th class="text-end" style="width: 120px;">Totale</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(voce, index) in getVociProposta(appuntamentoSelezionato)" :key="index">
                      <td>{{ voce.nome }}</td>
                      <td class="text-center">{{ voce.quantita }}</td>
                      <td class="text-end">{{ formatCurrency(voce.prezzoUnitario) }}</td>
                      <td class="text-end">{{ formatCurrency(voce.totale) }}</td>
                    </tr>
                  </tbody>
                  <tfoot class="table-light">
                    <tr class="fw-bold">
                      <td colspan="3" class="text-end">Totale Proposta</td>
                      <td class="text-end">{{ formatCurrency(getTotale(appuntamentoSelezionato)) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <p v-else class="text-muted">Nessuna voce disponibile</p>
            </div>

            <div class="mb-3" v-if="appuntamentoSelezionato.note">
              <h6>Note</h6>
              <p>{{ appuntamentoSelezionato.note }}</p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="chiudiModal">
              Chiudi
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAppuntamentoStore } from '@/stores/appuntamentoStore'
import { useToast } from 'vue-toastification'
import { Modal } from 'bootstrap'

const appuntamentoStore = useAppuntamentoStore()
const { isLoading, appuntamenti } = storeToRefs(appuntamentoStore)
const toast = useToast()

const modalDettagli = ref(null)
let modalInstance = null
const appuntamentoSelezionato = ref(null)

const filtroStato = ref('')
const cercaPaziente = ref('')

const appuntamentiFiltrati = computed(() => {
  let risultato = [...appuntamenti.value]

  // Filtro per stato
  if (filtroStato.value) {
    risultato = risultato.filter(app => app.stato === filtroStato.value)
  }

  // Filtro per paziente (email o cellulare)
  if (cercaPaziente.value) {
    const search = cercaPaziente.value.toLowerCase()
    risultato = risultato.filter(app => {
      const email = app.proposta?.preventivo_paziente?.email_paziente?.toLowerCase() || ''
      const cellulare = app.proposta?.preventivo_paziente?.cellulare_paziente?.toLowerCase() || ''
      return email.includes(search) || cellulare.includes(search)
    })
  }

  return risultato
})

onMounted(async () => {
  modalInstance = new Modal(modalDettagli.value)
  await caricaAppuntamenti()
})

async function caricaAppuntamenti() {
  await appuntamentoStore.fetchAppuntamenti()
}

function applicaFiltri() {
  // I filtri sono reattivi, non serve fare nulla
}

function resetFiltri() {
  filtroStato.value = ''
  cercaPaziente.value = ''
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString('it-IT', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

function formatTime(dateString) {
  const date = new Date(dateString)
  return date.toLocaleTimeString('it-IT', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

function getStatoLabel(stato) {
  const labels = {
    nuovo: 'Nuovo',
    visualizzato: 'Visualizzato',
    assente: 'Assente',
    cancellato: 'Cancellato'
  }
  return labels[stato] || stato
}

function getStatoBadgeClass(stato) {
  const classes = {
    nuovo: 'badge bg-info',
    visualizzato: 'badge bg-primary',
    assente: 'badge bg-warning',
    cancellato: 'badge bg-danger'
  }
  return classes[stato] || 'badge bg-secondary'
}

async function cambiaStato(appuntamento, nuovoStato) {
  let messaggio = ''
  if (nuovoStato === 'cancellato') {
    messaggio = 'Sei sicuro di voler cancellare questo appuntamento?'
  } else if (nuovoStato === 'assente') {
    messaggio = 'Sei sicuro di voler segnare il paziente come assente?'
  }

  const conferma = confirm(messaggio)
  if (!conferma) return

  const result = await appuntamentoStore.aggiornaStatoAppuntamento(appuntamento.id, nuovoStato)

  if (result.success) {
    toast.success(result.message)
    await caricaAppuntamenti()
  } else {
    toast.error(result.message)
  }
}

async function mostraDettagli(appuntamento) {
  appuntamentoSelezionato.value = appuntamento
  modalInstance.show()

  // Marca come visualizzato se è nello stato 'nuovo'
  if (appuntamento.stato === 'nuovo') {
    await appuntamentoStore.marcaVisualizzato(appuntamento.id)
  }
}

function chiudiModal() {
  modalInstance.hide()
  appuntamentoSelezionato.value = null
}

function getVociProposta(appuntamento) {
  if (!appuntamento?.proposta?.json_proposta) return []

  const jsonProposta = appuntamento.proposta.json_proposta
  const voci = []

  if (jsonProposta.voci_proposta && Array.isArray(jsonProposta.voci_proposta)) {
    voci.push(...jsonProposta.voci_proposta.map(v => ({
      nome: v.prestazione_corrispondente || v.prestazione_originale || 'Voce',
      quantita: parseInt(v.quantità || v.quantita || 1),
      prezzoUnitario: parseFloat(v.prezzo || 0),
      totale: parseFloat(v.prezzo || 0) * parseInt(v.quantità || v.quantita || 1)
    })))
  }

  return voci
}

function getTotale(appuntamento) {
  if (!appuntamento?.proposta?.json_proposta) return 0

  if (appuntamento.proposta.json_proposta.totale_proposta) {
    return parseFloat(appuntamento.proposta.json_proposta.totale_proposta)
  }

  const voci = getVociProposta(appuntamento)
  return voci.reduce((sum, voce) => sum + voce.totale, 0)
}

function formatCurrency(value) {
  return new Intl.NumberFormat('it-IT', {
    style: 'currency',
    currency: 'EUR'
  }).format(value || 0)
}
</script>

<style scoped>
.table th {
  background-color: #f8f9fa;
  font-weight: 600;
}
</style>
