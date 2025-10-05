<script setup>
import { ref, computed, onUnmounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { Form, Field, ErrorMessage } from 'vee-validate'
import * as yup from 'yup'
import { useToast } from 'vue-toastification'
import { usePreventivoStore } from '@/stores/preventivoStore'
import { storeToRefs } from 'pinia'
import logoSrc from '@/assets/images/logo-IDM.png'

const preventivoStore = usePreventivoStore()
const {
  statoElaborazione,
  vociPreventivo,
  messaggioErrore,
  isLoading,
  uploadProgress,
  isElaborazioneCompletata,
  isInAttesaDatiPaziente,
  isRicercaProposte,
  isPropostePronte,
  haErrore,
  isSenzaProposte,
  proposte
} = storeToRefs(preventivoStore)

const toast = useToast()

// --- STATO DEL COMPONENTE ---
const preventivoFile = ref(null)
const isDragging = ref(false)
const formUploadRef = ref(null)
const formDatiPazienteRef = ref(null)
const pollingInterval = ref(null)
const vociEditate = ref([])
const showModalDettagli = ref(false)
const propostaSelezionata = ref(null)

// --- VALIDATION SCHEMAS ---
const uploadSchema = yup.object({
  preventivoFile: yup
    .mixed()
    .required('È necessario caricare un file.')
    .test('fileSize', 'Il file è troppo grande (max 10MB)', (value) =>
      !value || (value && value.size <= 10 * 1024 * 1024)
    )
    .test('fileType', 'Formato non supportato (accettati: PDF, JPG, PNG)', (value) =>
      !value || (value && ['application/pdf', 'image/jpeg', 'image/png'].includes(value.type))
    ),
})

const datiPazienteSchema = yup.object({
  email: yup.string().required("L'email è obbligatoria").email('Email non valida'),
  cellulare: yup.string().required('Il cellulare è obbligatorio').min(9, 'Numero non valido'),
  nome: yup.string().required('Il nome è obbligatorio'),
  cognome: yup.string().required('Il cognome è obbligatorio'),
  indirizzo: yup.string().required("L'indirizzo è obbligatorio"),
  citta: yup.string().required('La città è obbligatoria'),
  provincia: yup.string().required('La provincia è obbligatoria').length(2, 'La sigla deve essere di 2 lettere'),
  cap: yup.string().required('Il CAP è obbligatorio').length(5, 'Il CAP deve essere di 5 cifre'),
})

// --- GESTIONE FILE ---
const handleFileChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    preventivoFile.value = file
    formUploadRef.value.setFieldValue('preventivoFile', file)
  }
}

const handleDrop = (event) => {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file) {
    preventivoFile.value = file
    formUploadRef.value.setFieldValue('preventivoFile', file)
  }
}

const removeFile = () => {
  preventivoFile.value = null
  formUploadRef.value?.setFieldValue('preventivoFile', null)
}

// --- STEP 1: UPLOAD FILE ---
const handleUpload = async () => {
  if (!preventivoFile.value) {
    toast.error('Seleziona un file prima di procedere')
    return
  }

  const response = await preventivoStore.caricaPreventivo(preventivoFile.value)

  if (response.success) {
    toast.info('Il tuo preventivo è in fase di analisi. Attendi...')
    startPolling(preventivoStore.controllaStato)
  } else {
    toast.error(response.message)
  }
}

// --- POLLING ---
const startPolling = (action) => {
  stopPolling()
  action() // Esegui subito
  pollingInterval.value = setInterval(action, 5000) // Poi ogni 5 secondi
}

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
  }
}

// Watch per gestire i cambiamenti di stato
watch(statoElaborazione, (newStatus) => {
  if (newStatus === 'completato') {
    toast.success('Abbiamo analizzato il tuo preventivo! Controlla le voci qui sotto.')
    stopPolling()
    // Inizializza le voci editate
    if (vociPreventivo.value) {
      vociEditate.value = JSON.parse(JSON.stringify(vociPreventivo.value))
    }
  }

  if (newStatus === 'proposte_pronte') {
    console.log('Proposte ricevute:', proposte.value)
    toast.success('Abbiamo trovato delle proposte per te!')
    stopPolling()
  }

  if (newStatus === 'senza_proposte') {
    toast.warning('Non abbiamo trovato proposte nella tua zona al momento.')
    stopPolling()
  }

  if (newStatus === 'errore') {
    toast.error(messaggioErrore.value || 'Si è verificato un errore')
    stopPolling()
  }
})

// --- STEP 2: CONFERMA VOCI ---
const addVoce = () => {
  vociEditate.value.push({ prestazione: '', quantità: 1, prezzo: 0 })
}

const removeVoce = (index) => {
  vociEditate.value.splice(index, 1)
}

const formatCurrency = (value) => {
  if (isNaN(parseFloat(value))) return '0,00'
  return parseFloat(value).toLocaleString('it-IT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const totalPreventivo = computed(() => {
  return vociEditate.value.reduce((acc, voce) => acc + parseFloat(voce.prezzo || 0), 0)
})

const handleConfermaVoci = async () => {
  const response = await preventivoStore.confermaVoci(vociEditate.value)

  if (response.success) {
    toast.success('Voci confermate! Ora inserisci i tuoi dati personali.')
  } else {
    toast.error(response.message)
  }
}

// --- STEP 3: SALVA DATI PAZIENTE ---
const handleSalvaDatiPaziente = async (values) => {
  const response = await preventivoStore.salvaDatiPaziente(values)

  if (response.success) {
    toast.info('Dati salvati! Stiamo cercando le migliori proposte per te.')
    startPolling(preventivoStore.controllaProposte)
  } else {
    toast.error(response.message)
  }
}

// --- MODALE DETTAGLI ---
const apriDettagli = (proposta) => {
  console.log('Proposta selezionata:', proposta)
  console.log('JSON proposta:', proposta.json_proposta)
  propostaSelezionata.value = proposta
  showModalDettagli.value = true
}

const chiudiDettagli = () => {
  showModalDettagli.value = false
  propostaSelezionata.value = null
}

const calcolaTotaleOriginale = computed(() => {
  if (!vociPreventivo.value) return 0
  return vociPreventivo.value.reduce((acc, voce) => acc + parseFloat(voce.prezzo || 0), 0)
})

const calcolaTotaleProposta = (proposta) => {
  if (!proposta?.json_proposta) return 0
  // Se il totale è già calcolato nel JSON, usalo
  if (proposta.json_proposta.totale_proposta !== undefined) {
    return parseFloat(proposta.json_proposta.totale_proposta || 0)
  }
  // Altrimenti calcolalo dalle voci
  if (proposta.json_proposta.voci_proposta) {
    return proposta.json_proposta.voci_proposta.reduce((acc, voce) => acc + parseFloat(voce.prezzo || 0), 0)
  }
  return 0
}

// --- RESET ---
const resetAll = () => {
  preventivoStore.reset()
  preventivoFile.value = null
  vociEditate.value = []
  stopPolling()
}

// --- CICLO DI VITA ---
onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="container-fluid px-3 px-md-4 py-4 py-md-5">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-10 col-xl-9">
        <!-- Logo -->
        <div class="text-center mb-3 mb-md-4">
          <RouterLink to="/">
            <img :src="logoSrc" alt="Il Dentista Migliore Logo" class="logo-responsive">
          </RouterLink>
        </div>

        <h1 class="responsive-title fw-bold text-center mb-2 mb-md-3">Carica il Tuo Preventivo</h1>
        <p class="responsive-lead text-muted text-center mb-4 mb-md-5 px-2">
          Carica il tuo preventivo e ricevi proposte personalizzate dai migliori studi medici della tua zona
        </p>

        <div class="card border-0 shadow-sm">
          <div class="card-body p-3 p-sm-4 p-md-5">

            <!-- STEP 1: UPLOAD FILE -->
            <div v-if="!statoElaborazione || statoElaborazione === 'caricato' || haErrore">
              <!-- Messaggio errore -->
              <div v-if="haErrore" class="alert alert-danger mb-3 mb-md-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                  <div>
                    <p class="fw-bold mb-1 mb-sm-0 small">Si è verificato un errore</p>
                    <small class="d-block d-sm-inline">{{ messaggioErrore }}</small>
                  </div>
                  <button @click="resetAll" class="btn btn-sm btn-danger flex-shrink-0">Riprova</button>
                </div>
              </div>

              <Form @submit="handleUpload" :validation-schema="uploadSchema" ref="formUploadRef">
                <div
                  class="upload-area text-center p-3 p-sm-4 p-md-5 rounded-3 border-2"
                  :class="{ dragging: isDragging, 'has-file': preventivoFile }"
                  @dragover.prevent="isDragging = true"
                  @dragleave.prevent="isDragging = false"
                  @drop.prevent="handleDrop"
                >
                  <div v-if="!preventivoFile">
                    <i class="fa-solid fa-cloud-arrow-up fa-2x fa-md-3x text-muted mb-2 mb-md-3"></i>
                    <h5 class="fw-bold fs-6 fs-md-5">Trascina il tuo file qui</h5>
                    <p class="text-muted small mb-2">oppure</p>
                    <label for="file-input" class="btn btn-primary btn-sm btn-md-md">Seleziona File</label>
                    <input
                      type="file"
                      id="file-input"
                      @change="handleFileChange"
                      accept=".pdf,.jpg,.jpeg,.png"
                      class="d-none"
                    />
                    <p class="text-muted small mt-2 mt-md-3 mb-0">Formati accettati: PDF, JPG, PNG (max 10MB)</p>
                  </div>
                  <div v-else class="file-preview">
                    <i class="fa-solid fa-file-invoice fa-2x fa-md-3x text-primary mb-2 mb-md-3"></i>
                    <p class="fw-bold mb-1 text-truncate px-2" :title="preventivoFile.name">{{ preventivoFile.name }}</p>
                    <p class="text-muted small mb-2">{{ (preventivoFile.size / 1024).toFixed(2) }} KB</p>
                    <button type="button" @click="removeFile" class="btn btn-sm btn-danger mt-1 mt-md-2">
                      Rimuovi
                    </button>
                  </div>
                </div>
                <ErrorMessage name="preventivoFile" class="text-danger small mt-2 d-block" />

                <!-- Progress bar -->
                <div v-if="uploadProgress > 0" class="mt-3">
                  <div class="progress">
                    <div
                      class="progress-bar"
                      role="progressbar"
                      :style="{ width: uploadProgress + '%' }"
                      :aria-valuenow="uploadProgress"
                      aria-valuemin="0"
                      aria-valuemax="100"
                    >
                      {{ uploadProgress }}%
                    </div>
                  </div>
                </div>

                <div class="d-grid d-sm-flex justify-content-sm-end mt-3 mt-md-4">
                  <button type="submit" class="btn btn-primary btn-lg" :disabled="isLoading || !preventivoFile">
                    <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                    <span class="d-none d-sm-inline">{{ isLoading ? 'Caricamento...' : 'Carica Preventivo' }}</span>
                    <span class="d-sm-none">{{ isLoading ? 'Caricamento...' : 'Carica' }}</span>
                  </button>
                </div>
              </Form>
            </div>

            <!-- LOADING: ELABORAZIONE IN CORSO -->
            <div v-else-if="statoElaborazione === 'in_elaborazione'" class="text-center p-3 p-sm-4 p-md-5">
              <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem"></div>
              <h4 class="mt-3 mt-md-4 fs-5 fs-md-4">Stiamo elaborando il tuo preventivo...</h4>
              <p class="text-muted small mb-0">Questa operazione potrebbe richiedere fino a un minuto.</p>
            </div>

            <!-- STEP 2: CONFERMA VOCI -->
            <div v-else-if="isElaborazioneCompletata">
              <h3 class="mb-2 mb-md-3 fs-4 fs-md-3">Verifica le voci del preventivo</h3>
              <p class="text-muted small mb-3 mb-md-4">
                Abbiamo estratto queste informazioni dal file che hai caricato. Controllale e, se necessario,
                modificane i valori prima di continuare.
              </p>

              <div class="table-responsive">
                <table class="table table-striped table-hover align-middle table-sm">
                  <thead class="table-light">
                    <tr>
                      <th scope="col" class="small">Prestazione</th>
                      <th scope="col" class="small" style="width: 80px">Qnt</th>
                      <th scope="col" class="small" style="width: 100px">Prezzo (€)</th>
                      <th scope="col" style="width: 50px"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(voce, index) in vociEditate" :key="index">
                      <td>
                        <input
                          type="text"
                          class="form-control form-control-sm"
                          v-model="voce.prestazione"
                          placeholder="Nome prestazione"
                        />
                      </td>
                      <td>
                        <input type="number" class="form-control form-control-sm" v-model.number="voce.quantità" min="1" />
                      </td>
                      <td>
                        <input
                          type="number"
                          class="form-control form-control-sm"
                          v-model.number="voce.prezzo"
                          step="0.01"
                          placeholder="0.00"
                        />
                      </td>
                      <td class="text-center">
                        <button @click="removeVoce(index)" class="btn btn-sm btn-outline-danger p-1" title="Rimuovi riga">
                          <i class="fa-solid fa-trash-can fa-xs"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <button @click="addVoce" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="fa-solid fa-plus me-1"></i> <span class="d-none d-sm-inline">Aggiungi riga</span><span class="d-sm-none">Aggiungi</span>
              </button>

              <div class="text-end mt-3 mt-md-4 fs-6 fs-md-5 fw-bold">Totale Stimato: € {{ formatCurrency(totalPreventivo) }}</div>

              <hr class="my-3 my-md-4" />

              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2">
                <button @click="resetAll" class="btn btn-secondary order-2 order-sm-1">
                  <span class="d-none d-sm-inline">Annulla e ricarica</span>
                  <span class="d-sm-none">Annulla</span>
                </button>
                <button @click="handleConfermaVoci" class="btn btn-primary btn-lg order-1 order-sm-2" :disabled="isLoading">
                  <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                  Conferma Voci
                </button>
              </div>
            </div>

            <!-- STEP 3: INSERIMENTO DATI PAZIENTE -->
            <div v-else-if="isInAttesaDatiPaziente">
              <h3 class="mb-2 mb-md-3 fs-4 fs-md-3">I tuoi dati personali</h3>
              <p class="text-muted small mb-3 mb-md-4">
                Per completare la richiesta e ricevere le proposte, abbiamo bisogno di alcuni tuoi dati.
              </p>

              <Form @submit="handleSalvaDatiPaziente" :validation-schema="datiPazienteSchema" ref="formDatiPazienteRef">
                <div class="row g-2 g-md-3">
                  <div class="col-12 col-md-6">
                    <label class="form-label small">Email *</label>
                    <Field name="email" type="email" class="form-control form-control-sm" />
                    <ErrorMessage name="email" class="text-danger small" />
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label small">Cellulare *</label>
                    <Field name="cellulare" type="tel" class="form-control form-control-sm" />
                    <ErrorMessage name="cellulare" class="text-danger small" />
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label small">Nome *</label>
                    <Field name="nome" type="text" class="form-control form-control-sm" />
                    <ErrorMessage name="nome" class="text-danger small" />
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label small">Cognome *</label>
                    <Field name="cognome" type="text" class="form-control form-control-sm" />
                    <ErrorMessage name="cognome" class="text-danger small" />
                  </div>

                  <div class="col-12">
                    <label class="form-label small">Indirizzo *</label>
                    <Field name="indirizzo" type="text" class="form-control form-control-sm" placeholder="Via/Piazza, numero civico" />
                    <ErrorMessage name="indirizzo" class="text-danger small" />
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label small">Città *</label>
                    <Field name="citta" type="text" class="form-control form-control-sm" />
                    <ErrorMessage name="citta" class="text-danger small" />
                  </div>

                  <div class="col-6 col-md-3">
                    <label class="form-label small">Provincia *</label>
                    <Field name="provincia" type="text" class="form-control form-control-sm" maxlength="2" placeholder="ES: MI" />
                    <ErrorMessage name="provincia" class="text-danger small" />
                  </div>

                  <div class="col-6 col-md-3">
                    <label class="form-label small">CAP *</label>
                    <Field name="cap" type="text" class="form-control form-control-sm" maxlength="5" />
                    <ErrorMessage name="cap" class="text-danger small" />
                  </div>
                </div>

                <hr class="my-3 my-md-4" />

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2">
                  <button type="button" @click="resetAll" class="btn btn-secondary order-2 order-sm-1">Annulla</button>
                  <button type="submit" class="btn btn-primary btn-lg order-1 order-sm-2" :disabled="isLoading">
                    <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                    <span class="d-none d-sm-inline">Invia e Cerca Proposte</span>
                    <span class="d-sm-none">Invia</span>
                  </button>
                </div>
              </Form>
            </div>

            <!-- LOADING: RICERCA PROPOSTE -->
            <div v-else-if="isRicercaProposte" class="text-center p-3 p-sm-4 p-md-5">
              <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem"></div>
              <h4 class="mt-3 mt-md-4 fs-5 fs-md-4">Stiamo cercando le migliori proposte per te...</h4>
              <p class="text-muted small mb-0">Ti contatteremo a breve con le offerte degli studi medici della tua zona.</p>
            </div>

            <!-- SUCCESS: PROPOSTE PRONTE -->
            <div v-else-if="isPropostePronte">
              <div class="text-center mb-3 mb-md-4">
                <i class="fa-solid fa-circle-check fa-3x fa-md-4x text-success mb-2 mb-md-3"></i>
                <h3 class="fw-bold fs-4 fs-md-3">Proposte trovate!</h3>
                <p class="text-muted small mb-0">
                  Abbiamo trovato {{ proposte.length }} {{ proposte.length === 1 ? 'proposta' : 'proposte' }} per te
                </p>
              </div>

              <div class="row g-3 g-md-4">
                <div v-for="proposta in proposte" :key="proposta.id" class="col-12 col-md-6">
                  <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-3 p-md-4">
                      <h5 class="card-title fw-bold text-primary mb-2 mb-md-3 fs-6 fs-md-5">
                        {{ proposta.medico?.anagrafica_medico?.ragione_sociale || 'Studio Medico' }}
                      </h5>
                      <p class="text-muted small mb-2 mb-md-3">
                        <i class="fa-solid fa-location-dot me-1 me-md-2"></i>
                        <span class="d-inline-block" style="max-width: calc(100% - 20px)">
                          {{ proposta.medico?.anagrafica_medico?.indirizzo || 'Indirizzo non disponibile' }}
                        </span>
                      </p>
                      <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
                        <span class="text-muted small">Prezzo Totale:</span>
                        <span class="fs-5 fs-md-4 fw-bold text-success">
                          € {{ formatCurrency(calcolaTotaleProposta(proposta)) }}
                        </span>
                      </div>
                      <button @click="apriDettagli(proposta)" class="btn btn-outline-primary w-100 btn-sm">
                        <i class="fa-solid fa-eye me-1 me-md-2"></i><span class="d-none d-sm-inline">Vedi Dettagli</span><span class="d-sm-none">Dettagli</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="text-center mt-3 mt-md-4">
                <button @click="resetAll" class="btn btn-secondary btn-sm">
                  <span class="d-none d-sm-inline">Carica un altro preventivo</span>
                  <span class="d-sm-none">Nuovo preventivo</span>
                </button>
              </div>
            </div>

            <!-- NO PROPOSTE -->
            <div v-else-if="isSenzaProposte" class="text-center p-3 p-sm-4 p-md-5">
              <i class="fa-solid fa-circle-exclamation fa-3x fa-md-4x text-warning mb-3 mb-md-4"></i>
              <h3 class="fw-bold fs-4 fs-md-3">Nessuna proposta disponibile</h3>
              <p class="text-muted small mb-3">
                Al momento non ci sono studi disponibili nella tua zona. Riprova più tardi o contattaci direttamente.
              </p>
              <button @click="resetAll" class="btn btn-primary mt-2 mt-md-3">
                <span class="d-none d-sm-inline">Carica un altro preventivo</span>
                <span class="d-sm-none">Nuovo preventivo</span>
              </button>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- MODALE CONFRONTO PREVENTIVO-PROPOSTA -->
    <div v-if="showModalDettagli" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5)">
      <div class="modal-dialog modal-fullscreen-sm-down modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header py-2 py-md-3">
            <h5 class="modal-title fs-6 fs-md-5">Confronto Preventivo - Proposta</h5>
            <button type="button" class="btn-close" @click="chiudiDettagli"></button>
          </div>
          <div class="modal-body p-2 p-md-3">
            <div v-if="propostaSelezionata" class="row g-2 g-md-3">
              <!-- Preventivo Originale -->
              <div class="col-12 col-md-6">
                <h5 class="fw-bold text-primary mb-2 mb-md-3 fs-6 fs-md-5">
                  <i class="fa-solid fa-file-invoice me-1 me-md-2"></i>
                  <span class="d-none d-sm-inline">Preventivo Originale</span>
                  <span class="d-sm-none">Originale</span>
                </h5>
                <div class="card bg-light">
                  <div class="card-body p-2 p-md-3">
                    <div class="table-responsive">
                      <table class="table table-sm mb-0">
                        <thead>
                          <tr>
                            <th class="small">Prestazione</th>
                            <th class="small" style="width: 40px">Qnt</th>
                            <th class="text-end small" style="width: 70px">Prezzo</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(voce, index) in vociPreventivo" :key="'orig-' + index">
                            <td class="small">{{ voce.prestazione }}</td>
                            <td class="small">{{ voce.quantità }}</td>
                            <td class="text-end small">€ {{ formatCurrency(voce.prezzo) }}</td>
                          </tr>
                        </tbody>
                        <tfoot>
                          <tr class="fw-bold">
                            <td class="small" colspan="2">Totale</td>
                            <td class="text-end small">€ {{ formatCurrency(calcolaTotaleOriginale) }}</td>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Proposta del Medico -->
              <div class="col-12 col-md-6">
                <h5 class="fw-bold text-success mb-2 mb-md-3 fs-6 fs-md-5">
                  <i class="fa-solid fa-handshake me-1 me-md-2"></i>
                  <span class="d-none d-sm-inline">Proposta - {{ propostaSelezionata.medico?.anagrafica_medico?.ragione_sociale }}</span>
                  <span class="d-sm-none">Proposta</span>
                </h5>
                <div class="card bg-light">
                  <div class="card-body p-2 p-md-3">
                    <div class="table-responsive">
                      <table class="table table-sm mb-0">
                        <thead>
                          <tr>
                            <th class="small">Prestazione</th>
                            <th class="small" style="width: 40px">Qnt</th>
                            <th class="text-end small" style="width: 70px">Prezzo</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(voce, index) in propostaSelezionata.json_proposta?.voci_proposta" :key="'prop-' + index">
                            <td class="small">
                              <div>{{ voce.prestazione_corrispondente || voce.prestazione_originale }}</div>
                              <small v-if="voce.prestazione_corrispondente !== voce.prestazione_originale" class="text-muted d-block" style="font-size: 0.7rem">
                                (era: {{ voce.prestazione_originale }})
                              </small>
                            </td>
                            <td class="small">{{ voce.quantità }}</td>
                            <td class="text-end small">€ {{ formatCurrency(voce.prezzo) }}</td>
                          </tr>
                        </tbody>
                        <tfoot>
                          <tr class="fw-bold">
                            <td class="small" colspan="2">Totale</td>
                            <td class="text-end text-success small">
                              € {{ formatCurrency(calcolaTotaleProposta(propostaSelezionata)) }}
                            </td>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Risparmio -->
            <div v-if="propostaSelezionata" class="alert alert-info mt-2 mt-md-3 mb-0 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
              <span class="fw-bold small">Risparmio</span>
              <span class="fs-6 fs-md-5 fw-bold">
                € {{ formatCurrency(calcolaTotaleOriginale - calcolaTotaleProposta(propostaSelezionata)) }}
              </span>
            </div>
          </div>
          <div class="modal-footer py-2 py-md-3">
            <button type="button" class="btn btn-secondary btn-sm" @click="chiudiDettagli">Chiudi</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Logo responsive */
.logo-responsive {
  height: 2.5rem;
}

@media (min-width: 768px) {
  .logo-responsive {
    height: 3rem;
  }
}

/* Titoli responsive */
.responsive-title {
  font-size: 1.75rem;
}

@media (min-width: 576px) {
  .responsive-title {
    font-size: 2.25rem;
  }
}

@media (min-width: 768px) {
  .responsive-title {
    font-size: 2.5rem;
  }
}

.responsive-lead {
  font-size: 1rem;
}

@media (min-width: 768px) {
  .responsive-lead {
    font-size: 1.125rem;
  }
}

@media (min-width: 992px) {
  .responsive-lead {
    font-size: 1.25rem;
  }
}

/* Upload area */
.upload-area {
  border: 2px dashed #dee2e6;
  transition: all 0.3s ease;
  background-color: #f8f9fa;
  min-height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
}

@media (min-width: 768px) {
  .upload-area {
    min-height: 250px;
  }
}

.upload-area.dragging {
  border-color: var(--bs-primary);
  background-color: rgba(var(--bs-primary-rgb), 0.1);
}

.upload-area.has-file {
  border-color: var(--bs-success);
  background-color: rgba(var(--bs-success-rgb), 0.1);
}

/* Tabelle responsive */
@media (max-width: 575.98px) {
  .table-responsive {
    font-size: 0.875rem;
  }

  .table-sm th,
  .table-sm td {
    padding: 0.375rem 0.25rem;
  }
}

/* Modal responsive */
@media (max-width: 575.98px) {
  .modal-fullscreen-sm-down {
    max-width: 100%;
    margin: 0;
  }

  .modal-fullscreen-sm-down .modal-content {
    height: 100vh;
    border: 0;
    border-radius: 0;
  }
}

/* Testi responsive nelle card */
@media (max-width: 575.98px) {
  .card-title {
    word-break: break-word;
  }
}
</style>
