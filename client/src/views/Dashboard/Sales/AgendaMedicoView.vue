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

    <!-- Controlli Navigazione Mese -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <button
              class="btn btn-outline-primary"
              @click="mesePrecedente"
              :disabled="!puoAndareMesePrecedente"
            >
              <i class="fas fa-chevron-left"></i>
            </button>
          </div>
          <div class="col text-center">
            <h4 class="mb-0">{{ nomeMesseCorrente }} {{ annoCorrente }}</h4>
          </div>
          <div class="col-auto">
            <button
              class="btn btn-outline-primary"
              @click="meseSuccessivo"
              :disabled="!puoAndareMeseSuccessivo"
            >
              <i class="fas fa-chevron-right"></i>
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

    <div v-else>
      <!-- Calendario -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
          <!-- Header giorni settimana -->
          <div class="calendar-header">
            <div class="calendar-day-name">Lun</div>
            <div class="calendar-day-name">Mar</div>
            <div class="calendar-day-name">Mer</div>
            <div class="calendar-day-name">Gio</div>
            <div class="calendar-day-name">Ven</div>
            <div class="calendar-day-name">Sab</div>
            <div class="calendar-day-name">Dom</div>
          </div>

          <!-- Griglia calendario -->
          <div class="calendar-grid">
            <div 
              v-for="giorno in giorniCalendario" 
              :key="giorno.data"
              :class="[
                'calendar-day',
                { 'other-month': !giorno.meseCorrente },
                { 'today': giorno.oggi },
                { 'selected': giorno.data === giornoSelezionato }
              ]"
              @click="selezionaGiorno(giorno)"
            >
              <div class="day-number">{{ giorno.numero }}</div>
              <div class="day-slots">
                <div v-if="giorno.meseCorrente && giorno.disponibili > 0" class="slot-indicator disponibile">
                  {{ giorno.disponibili }} slot
                </div>
                <div v-else-if="giorno.meseCorrente && giorno.totale > 0" class="slot-indicator occupato">
                  Completo
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lista Slot del Giorno Selezionato -->
      <div v-if="giornoSelezionato" class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">
            <i class="fas fa-calendar-day me-2"></i>
            Slot disponibili per {{ formatDateLong(giornoSelezionato) }}
          </h5>
        </div>
        <div class="card-body">
          <div v-if="slotsGiornoSelezionato.length === 0" class="text-center py-4">
            <i class="fas fa-calendar-times display-4 text-muted"></i>
            <p class="mt-3 text-muted">Nessuno slot disponibile per questo giorno</p>
          </div>

          <div v-else class="row g-3">
            <div 
              v-for="slot in slotsGiornoSelezionato" 
              :key="slot.id"
              class="col-md-4 col-lg-3"
            >
              <div 
                :class="[
                  'slot-card',
                  slot.disponibile ? 'disponibile' : 'occupato'
                ]"
                @click="slot.disponibile && confermaPrenotazione(slot)"
              >
                <div class="slot-time">
                  <i class="fas fa-clock me-2"></i>
                  {{ formatTimeOnly(slot.start_time) }} - {{ formatTimeOnly(slot.end_time) }}
                </div>
                <div class="slot-status">
                  <span v-if="slot.disponibile" class="badge bg-success">
                    <i class="fas fa-check-circle me-1"></i>
                    {{ slot.poltrone_libere }} {{ slot.poltrone_libere === 1 ? 'posto' : 'posti' }}
                  </span>
                  <span v-else class="badge bg-danger">
                    <i class="fas fa-times-circle me-1"></i>
                    Completo
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="card shadow-sm">
        <div class="card-body text-center py-5">
          <i class="fas fa-calendar-check display-1 text-muted"></i>
          <p class="mt-3 text-muted">Seleziona un giorno dal calendario per vedere gli slot disponibili</p>
        </div>
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
              <h6>Data e Ora</h6>
              <p class="mb-0">
                {{ formatDateLong(slotSelezionato.start_time) }}<br>
                <strong>{{ formatTimeOnly(slotSelezionato.start_time) }} - {{ formatTimeOnly(slotSelezionato.end_time) }}</strong>
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
import { ref, computed, onMounted, watch } from 'vue'
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

const meseCorrente = ref(new Date().getMonth())
const annoCorrente = ref(new Date().getFullYear())
const giornoSelezionato = ref(null)
const slotSelezionato = ref(null)
const noteAppuntamento = ref('')

const modalConferma = ref(null)
let modalInstance = null

const nomiMesi = [
  'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
  'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'
]

const nomeMesseCorrente = computed(() => nomiMesi[meseCorrente.value])

const oggi = new Date()
oggi.setHours(0, 0, 0, 0)

const puoAndareMesePrecedente = computed(() => {
  const dataControllo = new Date(annoCorrente.value, meseCorrente.value, 1)
  const oggi = new Date()
  oggi.setDate(1)
  oggi.setHours(0, 0, 0, 0)
  return dataControllo > oggi
})

const puoAndareMeseSuccessivo = computed(() => {
  const dataControllo = new Date(annoCorrente.value, meseCorrente.value, 1)
  const limite = new Date()
  limite.setMonth(limite.getMonth() + 3)
  limite.setDate(1)
  return dataControllo < limite
})

const giorniCalendario = computed(() => {
  const primoGiornoMese = new Date(annoCorrente.value, meseCorrente.value, 1)
  const ultimoGiornoMese = new Date(annoCorrente.value, meseCorrente.value + 1, 0)
  
  let giornoSettimana = primoGiornoMese.getDay()
  giornoSettimana = giornoSettimana === 0 ? 7 : giornoSettimana
  
  const giorni = []
  
  // Giorni del mese precedente
  const giorniPrecedenti = giornoSettimana - 1
  for (let i = giorniPrecedenti; i > 0; i--) {
    const data = new Date(annoCorrente.value, meseCorrente.value, -i + 1)
    giorni.push(creaOggettoGiorno(data, false))
  }
  
  // Giorni del mese corrente
  for (let i = 1; i <= ultimoGiornoMese.getDate(); i++) {
    const data = new Date(annoCorrente.value, meseCorrente.value, i)
    giorni.push(creaOggettoGiorno(data, true))
  }
  
  // Giorni del mese successivo per completare la griglia
  const giorniDaAggiungere = 42 - giorni.length
  for (let i = 1; i <= giorniDaAggiungere; i++) {
    const data = new Date(annoCorrente.value, meseCorrente.value + 1, i)
    giorni.push(creaOggettoGiorno(data, false))
  }
  
  return giorni
})

function creaOggettoGiorno(data, meseCorrente) {
  // Formatta la data in locale timezone invece di UTC
  const year = data.getFullYear()
  const month = String(data.getMonth() + 1).padStart(2, '0')
  const day = String(data.getDate()).padStart(2, '0')
  const dataStr = `${year}-${month}-${day}`

  const slots = agendaMedico.value.filter(slot =>
    slot.start_time.startsWith(dataStr)
  )

  const disponibili = slots.filter(s => s.disponibile).length
  const totale = slots.length

  const oggiData = new Date()
  oggiData.setHours(0, 0, 0, 0)

  return {
    numero: data.getDate(),
    data: dataStr,
    meseCorrente,
    oggi: data.getTime() === oggiData.getTime(),
    disponibili,
    totale,
  }
}

const slotsGiornoSelezionato = computed(() => {
  if (!giornoSelezionato.value) return []
  
  return agendaMedico.value
    .filter(slot => slot.start_time.startsWith(giornoSelezionato.value))
    .sort((a, b) => new Date(a.start_time) - new Date(b.start_time))
})

onMounted(async () => {
  modalInstance = new Modal(modalConferma.value)
  
  if (!propostaSelezionata.value) {
    toast.warning('Nessuna proposta selezionata')
    router.push({ name: 'sales-ricerca-proposte' })
    return
  }
  
  await caricaAgenda()
})

watch([meseCorrente, annoCorrente], async () => {
  await caricaAgenda()
  giornoSelezionato.value = null
})

async function caricaAgenda() {
  const medicoId = route.params.medicoId || propostaSelezionata.value?.medico_user_id
  
  if (!medicoId) {
    toast.error('ID medico non trovato')
    return
  }
  
  const primoGiorno = new Date(annoCorrente.value, meseCorrente.value, 1)
  const ultimoGiorno = new Date(annoCorrente.value, meseCorrente.value + 1, 0)
  
  const dataInizio = primoGiorno.toISOString().split('T')[0]
  const dataFine = ultimoGiorno.toISOString().split('T')[0]
  
  const result = await salesStore.getAgendaMedico(medicoId, dataInizio, dataFine)
  
  if (!result.success) {
    toast.error(result.message)
  }
}

function mesePrecedente() {
  if (meseCorrente.value === 0) {
    meseCorrente.value = 11
    annoCorrente.value--
  } else {
    meseCorrente.value--
  }
}

function meseSuccessivo() {
  if (meseCorrente.value === 11) {
    meseCorrente.value = 0
    annoCorrente.value++
  } else {
    meseCorrente.value++
  }
}

function selezionaGiorno(giorno) {
  if (!giorno.meseCorrente) return
  
  const dataGiorno = new Date(giorno.data)
  const oggi = new Date()
  oggi.setHours(0, 0, 0, 0)
  
  if (dataGiorno < oggi) {
    toast.warning('Non puoi selezionare un giorno passato')
    return
  }
  
  giornoSelezionato.value = giorno.data
}

function confermaPrenotazione(slot) {
  slotSelezionato.value = slot
  noteAppuntamento.value = ''
  modalInstance.show()
}

async function fissaAppuntamento() {
  if (!slotSelezionato.value || !propostaSelezionata.value) return
  
  const result = await salesStore.fissaAppuntamento(
    slotSelezionato.value.id,
    propostaSelezionata.value.id,
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

function formatDateLong(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString('it-IT', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

function formatTimeOnly(dateString) {
  const date = new Date(dateString)
  return date.toLocaleTimeString('it-IT', {
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<style scoped>
/* Calendar Styles */
.calendar-header {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  background-color: #f8f9fa;
  border-bottom: 2px solid #dee2e6;
}

.calendar-day-name {
  padding: 1rem;
  text-align: center;
  font-weight: 600;
  color: #495057;
  font-size: 0.875rem;
  text-transform: uppercase;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background-color: #dee2e6;
}

.calendar-day {
  background-color: white;
  min-height: 100px;
  padding: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
}

.calendar-day:hover {
  background-color: #f8f9fa;
  transform: scale(1.02);
}

.calendar-day.other-month {
  background-color: #f8f9fa;
  color: #adb5bd;
  cursor: default;
}

.calendar-day.other-month:hover {
  transform: none;
}

.calendar-day.today {
  background-color: #e7f3ff;
  border: 2px solid #0d6efd;
}

.calendar-day.selected {
  background-color: #0d6efd;
  color: white;
}

.calendar-day.selected .slot-indicator {
  background-color: white !important;
  color: #0d6efd !important;
}

.day-number {
  font-size: 1.125rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.day-slots {
  margin-top: auto;
}

.slot-indicator {
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  text-align: center;
  font-weight: 500;
}

.slot-indicator.disponibile {
  background-color: #d1e7dd;
  color: #0f5132;
}

.slot-indicator.occupato {
  background-color: #f8d7da;
  color: #842029;
}

/* Slot Card Styles */
.slot-card {
  border: 2px solid #dee2e6;
  border-radius: 8px;
  padding: 1rem;
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
}

.slot-card.disponibile {
  border-color: #198754;
  background-color: #d1e7dd;
}

.slot-card.disponibile:hover {
  background-color: #198754;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(25, 135, 84, 0.3);
}

.slot-card.occupato {
  border-color: #dc3545;
  background-color: #f8d7da;
  cursor: not-allowed;
  opacity: 0.6;
}

.slot-time {
  font-size: 1.125rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.slot-status {
  margin-top: 0.5rem;
}

@media (max-width: 768px) {
  .calendar-day {
    min-height: 80px;
    padding: 0.25rem;
  }
  
  .day-number {
    font-size: 0.875rem;
  }
  
  .slot-indicator {
    font-size: 0.625rem;
    padding: 0.125rem 0.25rem;
  }
}
</style>