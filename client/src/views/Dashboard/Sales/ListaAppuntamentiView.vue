<template>
  <div class="container-fluid py-4">
    <div class="row mb-4">
      <div class="col">
        <h2>Lista Appuntamenti</h2>
        <p class="text-muted">Gestisci tutti gli appuntamenti fissati</p>
      </div>
    </div>

    <!-- Filtri -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Filtra per Stato</label>
            <select v-model="filtroStato" class="form-select" @change="caricaAppuntamenti">
              <option value="">Tutti gli stati</option>
              <option value="confermato">Confermati</option>
              <option value="completato">Completati</option>
              <option value="cancellato">Cancellati</option>
            </select>
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
          <div v-if="appuntamenti.length === 0" class="text-center py-5">
            <i class="fas fa-calendar-times display-1 text-muted"></i>
            <p class="mt-3 text-muted">Nessun appuntamento trovato</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Data e Ora</th>
                  <th>Paziente</th>
                  <th>Medico</th>
                  <th>Contatti</th>
                  <th>Note</th>
                  <th>Stato</th>
                  <th>Azioni</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="app in appuntamenti" :key="app.id">
                  <td>
                    <span class="badge bg-secondary">#{{ app.id }}</span>
                  </td>
                  <td>
                    <div class="fw-bold">
                      {{ formatDate(app.slot_appuntamento.start_time) }}
                    </div>
                    <small class="text-muted">
                      {{ formatTime(app.slot_appuntamento.start_time) }} - 
                      {{ formatTime(app.slot_appuntamento.end_time) }}
                    </small>
                  </td>
                  <td>
                    <div class="fw-bold">
                      {{ app.proposta?.preventivo_paziente?.nome_paziente }} {{ app.proposta?.preventivo_paziente?.cognome_paziente || 'N/A' }}
                    </div>
                  </td>
                  <td>
                    <div>
                      {{ app.medico?.anagrafica_medico?.ragione_sociale || 'N/A' }}
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
                        v-if="app.stato === 'confermato'"
                        class="btn btn-sm btn-success"
                        @click="cambiaStato(app, 'completato')"
                        title="Segna come completato"
                      >
                        <i class="fas fa-check-circle"></i>
                      </button>
                      <button
                        v-if="app.stato === 'confermato'"
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
            <h5 class="modal-title">Dettagli Appuntamento #{{ appuntamentoSelezionato?.id }}</h5>
            <button type="button" class="btn-close" @click="chiudiModal"></button>
          </div>
          <div class="modal-body" v-if="appuntamentoSelezionato">
            <div class="row mb-3">
              <div class="col-md-6">
                <h6>Data e Ora</h6>
                <p>
                  {{ formatDate(appuntamentoSelezionato.slot_appuntamento.start_time) }}<br>
                  <strong>
                    {{ formatTime(appuntamentoSelezionato.slot_appuntamento.start_time) }} - 
                    {{ formatTime(appuntamentoSelezionato.slot_appuntamento.end_time) }}
                  </strong>
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

            <div class="row mb-3">
              <div class="col-md-6">
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
              <div class="col-md-6">
                <h6>Medico</h6>
                <p>
                  <strong>Studio:</strong> 
                  {{ appuntamentoSelezionato.medico?.anagrafica_medico?.ragione_sociale || 'N/A' }}<br>
                  <strong>Nome:</strong> 
                  {{ appuntamentoSelezionato.medico?.name || 'N/A' }}<br>
                  <strong>Email:</strong> 
                  {{ appuntamentoSelezionato.medico?.email || 'N/A' }}
                </p>
              </div>
            </div>

            <div class="mb-3" v-if="appuntamentoSelezionato.note">
              <h6>Note</h6>
              <p class="mb-0">{{ appuntamentoSelezionato.note }}</p>
            </div>

            <div class="mb-3">
              <h6>Informazioni Proposta</h6>
              <p>
                <strong>ID Proposta:</strong> #{{ appuntamentoSelezionato.proposta_id }}<br>
                <strong>Stato Proposta:</strong>
                <span class="badge bg-success">Accettata</span>
              </p>
            </div>

            <!-- Dettaglio Voci Proposta -->
            <div class="mb-3" v-if="appuntamentoSelezionato.proposta?.json_proposta?.voci_proposta">
              <h6>Dettaglio Prestazioni</h6>
              <div class="table-responsive">
                <table class="table table-sm table-bordered">
                  <thead class="table-light">
                    <tr>
                      <th>Prestazione</th>
                      <th class="text-center" style="width: 100px;">Quantità</th>
                      <th class="text-end" style="width: 120px;">Prezzo</th>
                      <th class="text-end" style="width: 120px;">Totale</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(voce, index) in appuntamentoSelezionato.proposta.json_proposta.voci_proposta" :key="index">
                      <td>{{ voce.prestazione_corrispondente || voce.prestazione_originale }}</td>
                      <td class="text-center">{{ voce.quantità }}</td>
                      <td class="text-end">€ {{ formatPrice(voce.prezzo) }}</td>
                      <td class="text-end fw-bold">€ {{ formatPrice(voce.prezzo * voce.quantità) }}</td>
                    </tr>
                  </tbody>
                  <tfoot class="table-light">
                    <tr>
                      <th colspan="3" class="text-end">TOTALE PROPOSTA</th>
                      <th class="text-end text-success fs-5">
                        € {{ formatPrice(appuntamentoSelezionato.proposta.json_proposta.totale_proposta) }}
                      </th>
                    </tr>
                  </tfoot>
                </table>
              </div>
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
import { ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAppuntamentoStore } from '@/stores/appuntamentoStore'
import { useToast } from 'vue-toastification'
import { Modal } from 'bootstrap'

const appuntamentoStore = useAppuntamentoStore()
const { isLoading, appuntamenti } = storeToRefs(appuntamentoStore)
const toast = useToast()

const filtroStato = ref('')
const modalDettagli = ref(null)
let modalInstance = null
const appuntamentoSelezionato = ref(null)

onMounted(async () => {
  modalInstance = new Modal(modalDettagli.value)
  await caricaAppuntamenti()
})

async function caricaAppuntamenti() {
  await appuntamentoStore.fetchAppuntamenti(filtroStato.value || null)
}

function formatDate(dateString) {
  const date = new Date(dateString)
  console.log(date)
  return date.toLocaleDateString('it-IT', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

function formatTime(dateString) {
  const date = new Date(dateString)
  console.log(date)
  return date.toLocaleTimeString('it-IT', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function getStatoLabel(stato) {
  const labels = {
    confermato: 'Confermato',
    completato: 'Completato',
    cancellato: 'Cancellato'
  }
  return labels[stato] || stato
}

function getStatoBadgeClass(stato) {
  const classes = {
    confermato: 'badge bg-primary',
    completato: 'badge bg-success',
    cancellato: 'badge bg-danger'
  }
  return classes[stato] || 'badge bg-secondary'
}

async function cambiaStato(appuntamento, nuovoStato) {
  const conferma = nuovoStato === 'cancellato' 
    ? confirm('Sei sicuro di voler cancellare questo appuntamento?')
    : confirm('Sei sicuro di voler segnare questo appuntamento come completato?')

  if (!conferma) return

  const result = await appuntamentoStore.aggiornaStatoAppuntamento(appuntamento.id, nuovoStato)
  
  if (result.success) {
    toast.success(result.message)
    await caricaAppuntamenti()
  } else {
    toast.error(result.message)
  }
}

function mostraDettagli(appuntamento) {
  appuntamentoSelezionato.value = appuntamento
  modalInstance.show()
}

function chiudiModal() {
  modalInstance.hide()
  appuntamentoSelezionato.value = null
}

function formatPrice(price) {
  return new Intl.NumberFormat('it-IT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(price)
}
</script>

<style scoped>
.table th {
  background-color: #f8f9fa;
  font-weight: 600;
  white-space: nowrap;
}

.table td {
  vertical-align: middle;
}
</style>