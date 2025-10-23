<template>
  <div class="container-fluid py-4">
    <div class="row mb-4">
      <div class="col">
        <h2>Appuntamenti Futuri</h2>
        <p class="text-muted">I tuoi prossimi appuntamenti confermati</p>
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
          <div v-if="appuntamentiFuturi.length === 0" class="text-center py-5">
            <i class="fas fa-calendar-check display-1 text-muted"></i>
            <p class="mt-3 text-muted">Nessun appuntamento futuro</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Data e Ora</th>
                  <th>Paziente</th>
                  <th>Contatto</th>
                  <th>Note</th>
                  <th>Stato</th>
                  <th>Azioni</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="app in appuntamentiFuturi" :key="app.id">
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
            <h5 class="modal-title">Dettagli Appuntamento</h5>
            <button type="button" class="btn-close" @click="chiudiModal"></button>
          </div>
          <div class="modal-body" v-if="appuntamentoSelezionato">
            <div class="row mb-3">
              <div class="col-md-6">
                <h6>Data e Ora</h6>
                <p>
                  {{ formatDate(appuntamentoSelezionato.slot_appuntamento.start_time) }}<br>
                  {{ formatTime(appuntamentoSelezionato.slot_appuntamento.start_time) }} - 
                  {{ formatTime(appuntamentoSelezionato.slot_appuntamento.end_time) }}
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
import { ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAppuntamentoStore } from '@/stores/appuntamentoStore'
import { useToast } from 'vue-toastification'
import { Modal } from 'bootstrap'

const appuntamentoStore = useAppuntamentoStore()
const { isLoading, appuntamentiFuturi } = storeToRefs(appuntamentoStore)
const toast = useToast()

const modalDettagli = ref(null)
let modalInstance = null
const appuntamentoSelezionato = ref(null)

onMounted(async () => {
  modalInstance = new Modal(modalDettagli.value)
  await appuntamentoStore.fetchAppuntamentiFuturi()
})

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString('it-IT', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
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
    await appuntamentoStore.fetchAppuntamentiFuturi()
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

function getVociProposta(appuntamento) {
  if (!appuntamento?.proposta?.json_proposta) return []

  const jsonProposta = appuntamento.proposta.json_proposta
  const voci = []

  // Estrai le voci dal json_proposta
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

  // Usa il totale già calcolato dal backend se disponibile
  if (appuntamento.proposta.json_proposta.totale_proposta) {
    return parseFloat(appuntamento.proposta.json_proposta.totale_proposta)
  }

  // Altrimenti calcola dalla somma delle voci
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

pre {
  font-size: 0.875rem;
  max-height: 300px;
  overflow-y: auto;
}
</style>