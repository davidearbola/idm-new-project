<template>
  <div class="container-fluid py-4">
    <div class="row mb-4">
      <div class="col">
        <h2>Agenda Medico</h2>
        <div v-if="propostaSelezionata">
          <p class="text-muted mb-2">
            Fissa appuntamento per:
            <strong>{{ propostaSelezionata.preventivo_paziente?.nome_paziente }} {{ propostaSelezionata.preventivo_paziente?.cognome_paziente }}</strong>
          </p>
          <p class="text-muted">
            Medico:
            <strong>{{ propostaSelezionata.medico?.anagrafica_medico?.ragione_sociale }}</strong>
          </p>
        </div>
      </div>
      <div class="col-auto">
        <button class="btn btn-secondary" @click="tornaRicerca">
          <i class="fas fa-arrow-left me-2"></i>Torna alla Ricerca
        </button>
      </div>
    </div>

    <!-- Controlli Navigazione Settimana -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <button class="btn btn-outline-primary" @click="settimanaPrecedente">
              <i class="fas fa-chevron-left"></i> Settimana Precedente
            </button>
          </div>
          <div class="col text-center">
            <h4 class="mb-0">{{ periodoStringa }}</h4>
          </div>
          <div class="col-auto">
            <button class="btn btn-outline-primary" @click="settimanaSuccessiva">
              Settimana Successiva <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Caricamento agenda...</span>
      </div>
    </div>

    <div v-else-if="agendaMedico && agendaMedico.poltrone.length > 0">
      <!-- Tabs per Poltrone -->
      <div class="card shadow-sm">
        <div class="card-header p-0">
          <ul class="nav nav-tabs nav-fill" role="tablist">
            <li class="nav-item" v-for="(poltrona, index) in agendaMedico.poltrone" :key="index" role="presentation">
              <button
                :class="['nav-link', { active: poltronaAttivaIndex === index }]"
                type="button"
                @click="poltronaAttivaIndex = index"
              >
                <i class="fas fa-chair me-2"></i>
                {{ poltrona.nome_poltrona }}
              </button>
            </li>
          </ul>
        </div>

        <div class="card-body p-0">
          <!-- Contenuto Tab -->
          <div v-if="poltronaAttiva" class="tab-content">
            <div class="table-responsive">
              <table class="agenda-table">
                <thead>
                  <tr>
                    <th class="time-column">Orario</th>
                    <th v-for="giorno in poltronaAttiva.giorni" :key="giorno.data" class="day-column">
                      <div class="day-header">
                        <div class="day-name">{{ giorno.nome_giorno }}</div>
                        <div class="day-date">{{ formatDate(giorno.data) }}</div>
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="ora in orari" :key="ora">
                    <td class="time-cell">{{ ora }}</td>
                    <td v-for="giorno in poltronaAttiva.giorni" :key="giorno.data" class="slot-cell">
                      <div
                        v-if="getSlot(giorno.slots, ora)"
                        :class="getSlotClass(getSlot(giorno.slots, ora))"
                        @click="selezionaSlot(getSlot(giorno.slots, ora))"
                      >
                        <div v-if="getSlot(giorno.slots, ora).disponibile" class="slot-disponibile">
                          <i class="fas fa-check-circle me-1"></i>
                          {{ getSlot(giorno.slots, ora).starting_time }}
                        </div>
                        <div v-else class="slot-occupato">
                          <i class="fas fa-user me-1"></i>
                          <div class="paziente-nome">
                            {{ getSlot(giorno.slots, ora).appuntamento?.paziente }}
                          </div>
                          <div class="orario-small">{{ getSlot(giorno.slots, ora).starting_time }}</div>
                        </div>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card shadow-sm">
      <div class="card-body text-center py-5">
        <i class="fas fa-calendar-times display-1 text-muted"></i>
        <p class="mt-3 text-muted">Nessuna disponibilità configurata per questo medico</p>
      </div>
    </div>

    <!-- Modal Conferma Prenotazione -->
    <div class="modal fade" ref="modalConferma" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Conferma Appuntamento</h5>
            <button type="button" class="btn-close" @click="chiudiModal"></button>
          </div>
          <div class="modal-body" v-if="slotSelezionato && propostaSelezionata">
            <div class="alert alert-info">
              <i class="fas fa-info-circle me-2"></i>
              Stai per fissare un appuntamento
            </div>

            <div class="mb-3">
              <h6>Paziente</h6>
              <p class="mb-0">
                <strong>{{ propostaSelezionata.preventivo_paziente?.nome_paziente }} {{ propostaSelezionata.preventivo_paziente?.cognome_paziente }}</strong>
              </p>
            </div>

            <div class="mb-3">
              <h6>Poltrona</h6>
              <p class="mb-0">
                <strong>{{ poltronaAttiva?.nome_poltrona }}</strong>
              </p>
            </div>

            <div class="mb-3">
              <h6>Data e Ora</h6>
              <p class="mb-0">
                {{ formatDateLong(slotSelezionato.starting_datetime) }}<br>
                <strong>{{ slotSelezionato.starting_time }} - {{ slotSelezionato.ending_time }}</strong>
              </p>
            </div>

            <div class="mb-3">
              <label class="form-label">Note (opzionale)</label>
              <textarea
                v-model="noteAppuntamento"
                class="form-control"
                rows="3"
                placeholder="Aggiungi eventuali note per l'appuntamento..."
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="chiudiModal">
              Annulla
            </button>
            <button
              type="button"
              class="btn btn-primary"
              @click="fissaAppuntamento"
              :disabled="isLoading"
            >
              <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
              <i v-else class="fas fa-check-circle me-2"></i>
              Conferma Prenotazione
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
import { useSalesStore } from '@/stores/salesStore'
import { useToast } from 'vue-toastification'
import { useRouter, useRoute } from 'vue-router'
import { Modal } from 'bootstrap'

const salesStore = useSalesStore()
const { isLoading, agendaMedico, propostaSelezionata } = storeToRefs(salesStore)
const toast = useToast()
const router = useRouter()
const route = useRoute()

const dataInizioSettimana = ref(null)
const poltronaAttivaIndex = ref(0)
const slotSelezionato = ref(null)
const noteAppuntamento = ref('')

const modalConferma = ref(null)
let modalInstance = null

// Orari dalle 8:00 alle 19:00 con intervalli di 30 minuti
const orari = [
  '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
  '11:00', '11:30', '12:00', '12:30', '13:00', '13:30',
  '14:00', '14:30', '15:00', '15:30', '16:00', '16:30',
  '17:00', '17:30', '18:00', '18:30'
]

const poltronaAttiva = computed(() => {
  if (!agendaMedico.value || !agendaMedico.value.poltrone) return null
  return agendaMedico.value.poltrone[poltronaAttivaIndex.value]
})

const periodoStringa = computed(() => {
  if (!agendaMedico.value || !agendaMedico.value.periodo) return ''

  const dataInizio = new Date(agendaMedico.value.periodo.data_inizio)
  const dataFine = new Date(agendaMedico.value.periodo.data_fine)

  return `${dataInizio.toLocaleDateString('it-IT', { day: 'numeric', month: 'long' })} - ${dataFine.toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' })}`
})

onMounted(async () => {
  modalInstance = new Modal(modalConferma.value)

  if (!propostaSelezionata.value) {
    toast.warning('Nessuna proposta selezionata')
    router.push({ name: 'sales-ricerca-proposte' })
    return
  }

  // Inizializza con il lunedì di questa settimana
  const oggi = new Date()
  const giorno = oggi.getDay()
  const lunedi = new Date(oggi)
  lunedi.setDate(oggi.getDate() - (giorno === 0 ? 6 : giorno - 1))

  dataInizioSettimana.value = lunedi.toISOString().split('T')[0]

  await caricaAgenda()
})

// Funzione helper per normalizzare una data al Lunedì della settimana
function normalizzaAlLunedi(data) {
  const d = new Date(data)
  const giorno = d.getDay() // 0=Domenica, 1=Lunedì, ..., 6=Sabato
  const diff = giorno === 0 ? 6 : giorno - 1 // Calcola differenza da Lunedì
  d.setDate(d.getDate() - diff)
  return d.toISOString().split('T')[0]
}

async function caricaAgenda() {
  const medicoId = route.params.medicoId || propostaSelezionata.value?.medico_user_id

  if (!medicoId) {
    toast.error('ID medico non trovato')
    return
  }

  // Assicurati che la data sia sempre un Lunedì
  dataInizioSettimana.value = normalizzaAlLunedi(dataInizioSettimana.value)

  const result = await salesStore.getAgendaMedico(medicoId, dataInizioSettimana.value)

  if (!result.success) {
    toast.error(result.message)
  }
}

function settimanaPrecedente() {
  const data = new Date(dataInizioSettimana.value)
  data.setDate(data.getDate() - 7)
  dataInizioSettimana.value = normalizzaAlLunedi(data)
  caricaAgenda()
}

function settimanaSuccessiva() {
  const data = new Date(dataInizioSettimana.value)
  data.setDate(data.getDate() + 7)
  dataInizioSettimana.value = normalizzaAlLunedi(data)
  caricaAgenda()
}

function getSlot(slots, ora) {
  return slots.find(slot => slot.starting_time === ora)
}

function isSlotPassato(slot) {
  if (!slot) return false
  const slotDateTime = new Date(slot.starting_datetime)
  const now = new Date()
  return slotDateTime < now
}

function getSlotClass(slot) {
  if (!slot) return ''

  const passato = isSlotPassato(slot)

  return {
    'slot': true,
    'slot-verde': slot.disponibile && !passato,
    'slot-rosso': !slot.disponibile,
    'slot-passato': passato,
    'slot-clickable': slot.disponibile && !passato
  }
}

function selezionaSlot(slot) {
  if (!slot || !slot.disponibile) return

  // Verifica che lo slot non sia nel passato
  if (isSlotPassato(slot)) {
    toast.warning('Non è possibile fissare appuntamenti per date passate')
    return
  }

  slotSelezionato.value = slot
  noteAppuntamento.value = ''
  modalInstance.show()
}

async function fissaAppuntamento() {
  if (!slotSelezionato.value || !propostaSelezionata.value || !poltronaAttiva.value) return

  const result = await salesStore.fissaAppuntamento(
    propostaSelezionata.value.id,
    poltronaAttiva.value.poltrona_id,
    slotSelezionato.value.starting_datetime,
    slotSelezionato.value.ending_datetime,
    noteAppuntamento.value || null
  )

  if (result.success) {
    toast.success(result.message)
    chiudiModal()
    router.push({ name: 'sales-lista-appuntamenti' })
  } else {
    toast.error(result.message)
  }
}

function chiudiModal() {
  modalInstance.hide()
  slotSelezionato.value = null
  noteAppuntamento.value = ''
}

function tornaRicerca() {
  salesStore.resetPropostaSelezionata()
  router.push({ name: 'sales-ricerca-proposte' })
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString('it-IT', {
    day: '2-digit',
    month: '2-digit'
  })
}

function formatDateLong(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString('it-IT', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
</script>

<style scoped>
/* Tabs Styling */
.nav-tabs {
  border-bottom: 2px solid #dee2e6;
}

.nav-tabs .nav-link {
  color: #6c757d;
  border: none;
  border-bottom: 3px solid transparent;
  padding: 1rem 1.5rem;
  font-weight: 500;
  transition: all 0.3s;
}

.nav-tabs .nav-link:hover {
  border-bottom-color: #0d6efd;
  color: #0d6efd;
}

.nav-tabs .nav-link.active {
  color: #0d6efd;
  background-color: transparent;
  border-bottom-color: #0d6efd;
}

/* Agenda Table */
.agenda-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.agenda-table thead th {
  background-color: #f8f9fa;
  padding: 1rem;
  border: 1px solid #dee2e6;
  font-weight: 600;
  text-align: center;
  position: sticky;
  top: 0;
  z-index: 10;
}

.time-column {
  width: 80px;
  min-width: 80px;
}

.day-column {
  width: calc((100% - 80px) / 7);
  min-width: 120px;
}

.day-header {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.day-name {
  font-size: 0.875rem;
  font-weight: 600;
  text-transform: capitalize;
}

.day-date {
  font-size: 0.75rem;
  color: #6c757d;
  margin-top: 0.25rem;
}

.time-cell {
  padding: 0.75rem;
  text-align: center;
  font-weight: 500;
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  color: #495057;
}

.slot-cell {
  padding: 4px;
  border: 1px solid #dee2e6;
  height: 60px;
  vertical-align: middle;
}

.slot {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.5rem;
  border-radius: 4px;
  transition: all 0.2s;
  font-size: 0.75rem;
}

.slot-verde {
  background-color: #d1e7dd;
  color: #0f5132;
  border: 1px solid #badbcc;
}

.slot-rosso {
  background-color: #f8d7da;
  color: #842029;
  border: 1px solid #f5c2c7;
}

.slot-passato {
  opacity: 0.4;
  cursor: not-allowed;
  background-color: #e9ecef !important;
  color: #6c757d !important;
}

.slot-clickable {
  cursor: pointer;
}

.slot-clickable:hover {
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.slot-disponibile {
  text-align: center;
  font-weight: 500;
}

.slot-occupato {
  text-align: center;
}

.paziente-nome {
  font-weight: 600;
  font-size: 0.7rem;
  margin-bottom: 0.25rem;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-box-orient: vertical;
}

.orario-small {
  font-size: 0.65rem;
  opacity: 0.8;
}

@media (max-width: 1200px) {
  .agenda-table {
    font-size: 0.75rem;
  }

  .day-column {
    min-width: 100px;
  }

  .slot {
    padding: 0.25rem;
    font-size: 0.65rem;
  }

  .paziente-nome {
    font-size: 0.65rem;
  }

  .nav-tabs .nav-link {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
  }
}
</style>
