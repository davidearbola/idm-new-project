<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Form, Field, ErrorMessage } from 'vee-validate'
import * as yup from 'yup'
import { useToast } from 'vue-toastification'
import { useTelmarStore } from '@/stores/telmarStore'
import axios from 'axios'
import logoSrc from '@/assets/images/logo-IDM.png'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const telmarStore = useTelmarStore()

// Stati della UI
const currentStep = ref('initial') // initial | otp_request | otp_verify | show_proposals
const email = ref('')
const otpCode = ref('')
const proposte = ref([])
const preventivi = ref([])
const isLoading = ref(false)
const showModalDettagli = ref(false)
const propostaSelezionata = ref(null)
const showModalChiamata = ref(false)
const propostaPerChiamata = ref(null)
const isInvioChiamata = ref(false)
const formChiamataRef = ref(null)
const otpExpiry = ref(null)
const remainingTime = ref(0)
const timerInterval = ref(null)

// Validation schemas
const emailSchema = yup.object({
  email: yup.string().required("L'email è obbligatoria").email("Inserisci un'email valida"),
})

const otpSchema = yup.object({
  otp_code: yup.string().required('Il codice è obbligatorio').length(6, 'Il codice deve essere di 6 cifre'),
})

const chiamataSchema = yup.object({
  nome: yup.string().required('Il nome è obbligatorio').max(255),
  cognome: yup.string().required('Il cognome è obbligatorio').max(255),
  cellulare: yup.string().required('Il cellulare è obbligatorio').min(9, 'Il cellulare deve essere di almeno 9 cifre').max(20),
})

// Timer countdown
const startCountdown = (expiresInMinutes) => {
  otpExpiry.value = Date.now() + (expiresInMinutes * 60 * 1000)
  remainingTime.value = expiresInMinutes * 60

  if (timerInterval.value) {
    clearInterval(timerInterval.value)
  }

  timerInterval.value = setInterval(() => {
    const now = Date.now()
    const diff = Math.floor((otpExpiry.value - now) / 1000)

    if (diff <= 0) {
      clearInterval(timerInterval.value)
      remainingTime.value = 0
      toast.warning('Il codice OTP è scaduto. Richiedine uno nuovo.')
    } else {
      remainingTime.value = diff
    }
  }, 1000)
}

const formatTime = computed(() => {
  const minutes = Math.floor(remainingTime.value / 60)
  const seconds = remainingTime.value % 60
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
})

// Mounted: controlla se c'è un token nell'URL
onMounted(async () => {
  if (route.query.token) {
    // Accesso diretto con token da email
    await accessoConToken(route.query.token)
  }
})

watch(() => route.query.token, (newToken) => {
  if (newToken) {
    accessoConToken(newToken)
  }
})

// METODO 1: Accesso con token dall'email
const accessoConToken = async (token) => {
  isLoading.value = true

  try {
    const response = await axios.post('/api/preventivi/accesso-con-token', { token })

    if (response.data.success) {
      preventivi.value = [response.data.preventivo]
      proposte.value = response.data.proposte
      email.value = response.data.preventivo.email_paziente
      currentStep.value = 'show_proposals'
      toast.success('Proposte caricate con successo!')
    }
  } catch (error) {
    console.error('Errore accesso con token:', error)
    if (error.response?.status === 404) {
      toast.error('Link non valido o proposte non ancora disponibili')
    } else {
      toast.error('Errore durante il caricamento delle proposte')
    }
    // Rimanda alla schermata iniziale se il token non è valido
    router.push('/visualizza-proposte')
    currentStep.value = 'initial'
  } finally {
    isLoading.value = false
  }
}

// METODO 2: Richiedi OTP
const handleRichiediOtp = async (values) => {
  isLoading.value = true
  email.value = values.email

  try {
    const response = await axios.post('/api/preventivi/richiedi-otp', {
      email: values.email
    })

    if (response.data.success) {
      currentStep.value = 'otp_verify'
      toast.success(response.data.message)
      startCountdown(response.data.expires_in_minutes || 10)
    }
  } catch (error) {
    console.error('Errore richiesta OTP:', error)
    if (error.response?.status === 404) {
      toast.error('Nessuna proposta trovata per questa email')
    } else if (error.response?.status === 429) {
      toast.error(error.response.data.message || 'Troppi tentativi. Riprova più tardi.')
    } else {
      toast.error('Errore durante l\'invio del codice')
    }
  } finally {
    isLoading.value = false
  }
}

// METODO 3: Verifica OTP
const handleVerificaOtp = async (values) => {
  isLoading.value = true

  try {
    const response = await axios.post('/api/preventivi/verifica-otp', {
      email: email.value,
      otp_code: values.otp_code
    })

    if (response.data.success) {
      preventivi.value = response.data.preventivi
      proposte.value = response.data.proposte
      currentStep.value = 'show_proposals'
      toast.success('Accesso consentito!')

      // Pulisci il timer
      if (timerInterval.value) {
        clearInterval(timerInterval.value)
      }
    }
  } catch (error) {
    console.error('Errore verifica OTP:', error)
    if (error.response?.status === 400) {
      toast.error(error.response.data.message || 'Codice non corretto')
    } else if (error.response?.status === 404) {
      toast.error('Codice non valido o scaduto')
    } else if (error.response?.status === 410) {
      toast.error('Il codice è scaduto. Richiedi un nuovo codice.')
      tornaAllaRicerca()
    } else if (error.response?.status === 429) {
      toast.error(error.response.data.message || 'Troppi tentativi. Account bloccato.')
      tornaAllaRicerca()
    } else {
      toast.error('Errore durante la verifica del codice')
    }
  } finally {
    isLoading.value = false
  }
}

// UI Helpers
const tornaAllaRicerca = () => {
  currentStep.value = 'initial'
  proposte.value = []
  preventivi.value = []
  email.value = ''
  otpCode.value = ''

  if (timerInterval.value) {
    clearInterval(timerInterval.value)
  }

  // Rimuovi il token dall'URL se presente
  if (route.query.token) {
    router.push('/visualizza-proposte')
  }
}

const tornaAInserimentoEmail = () => {
  currentStep.value = 'initial'
  otpCode.value = ''

  if (timerInterval.value) {
    clearInterval(timerInterval.value)
  }
}

const apriDettagli = (proposta) => {
  propostaSelezionata.value = proposta
  showModalDettagli.value = true
}

const chiudiDettagli = () => {
  showModalDettagli.value = false
  propostaSelezionata.value = null
}

const formatCurrency = (value) => {
  if (isNaN(parseFloat(value))) return '0,00'
  return parseFloat(value).toLocaleString('it-IT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const calcolaTotaleProposta = (proposta) => {
  if (!proposta?.json_proposta) return 0
  if (proposta.json_proposta.totale_proposta !== undefined) {
    return parseFloat(proposta.json_proposta.totale_proposta || 0)
  }
  if (proposta.json_proposta.voci_proposta) {
    return proposta.json_proposta.voci_proposta.reduce((acc, voce) => acc + parseFloat(voce.prezzo || 0), 0)
  }
  return 0
}

const calcolaTotaleOriginale = computed(() => {
  if (!propostaSelezionata.value?.preventivo_paziente?.json_preventivo?.voci_preventivo) return 0
  return propostaSelezionata.value.preventivo_paziente.json_preventivo.voci_preventivo.reduce(
    (acc, voce) => acc + parseFloat(voce.prezzo || 0), 0
  )
})

// Richiesta chiamata
const apriModalChiamata = (proposta) => {
  propostaPerChiamata.value = proposta
  showModalChiamata.value = true
}

const chiudiModalChiamata = () => {
  showModalChiamata.value = false
  propostaPerChiamata.value = null
  if (formChiamataRef.value) {
    formChiamataRef.value.resetForm()
  }
}

const handleRichiediChiamata = async (values) => {
  if (!propostaPerChiamata.value) return

  isInvioChiamata.value = true

  const response = await telmarStore.richiediChiamata({
    proposta_id: propostaPerChiamata.value.id,
    nome: values.nome,
    cognome: values.cognome,
    cellulare: values.cellulare,
  })

  isInvioChiamata.value = false

  if (response.success) {
    toast.success(response.message)
    chiudiModalChiamata()
  } else {
    toast.error(response.message)
  }
}
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

        <h1 class="responsive-title fw-bold text-center mb-2 mb-md-3">Le Tue Proposte</h1>
        <p class="responsive-lead text-muted text-center mb-4 mb-md-5 px-2">
          Visualizza e confronta le proposte ricevute dagli studi medici
        </p>

        <div class="card border-0 shadow-sm">
          <div class="card-body p-3 p-sm-4 p-md-5">

            <!-- STEP 1: Form Email Iniziale -->
            <div v-if="currentStep === 'initial'">
              <div class="text-center mb-4">
                <i class="fa-solid fa-envelope-open-text fa-3x text-primary mb-3"></i>
                <h3 class="mb-2 fs-4 fs-md-3">Accedi alle tue proposte</h3>
                <p class="text-muted small mb-0">
                  Inserisci l'email che hai usato per caricare il preventivo
                </p>
              </div>

              <Form @submit="handleRichiediOtp" :validation-schema="emailSchema" v-slot="{ errors }">
                <div class="mb-3">
                  <label class="form-label fw-semibold">Email</label>
                  <Field
                    name="email"
                    type="email"
                    class="form-control"
                    :class="{ 'is-invalid': errors.email }"
                    placeholder="la-tua-email@esempio.it"
                  />
                  <ErrorMessage name="email" class="text-danger small" />
                  <div class="form-text">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    Ti invieremo un codice di verifica via email
                  </div>
                </div>

                <div class="d-grid">
                  <button type="submit" class="btn btn-primary btn-lg" :disabled="isLoading">
                    <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                    <i v-else class="fa-solid fa-paper-plane me-2"></i>
                    {{ isLoading ? 'Invio in corso...' : 'Invia Codice di Verifica' }}
                  </button>
                </div>
              </Form>

              <div class="text-center mt-4">
                <p class="text-muted small mb-0">
                  <i class="fa-solid fa-shield-halved me-1"></i>
                  I tuoi dati sono protetti e sicuri
                </p>
              </div>
            </div>

            <!-- STEP 2: Verifica OTP -->
            <div v-if="currentStep === 'otp_verify'">
              <div class="text-center mb-4">
                <i class="fa-solid fa-lock fa-3x text-success mb-3"></i>
                <h3 class="mb-2 fs-4 fs-md-3">Verifica il codice</h3>
                <p class="text-muted small mb-0">
                  Abbiamo inviato un codice di 6 cifre a<br>
                  <strong>{{ email }}</strong>
                </p>
              </div>

              <Form @submit="handleVerificaOtp" :validation-schema="otpSchema" v-slot="{ errors }">
                <div class="mb-3">
                  <label class="form-label fw-semibold">Codice di Verifica</label>
                  <Field
                    name="otp_code"
                    type="text"
                    class="form-control form-control-lg text-center"
                    :class="{ 'is-invalid': errors.otp_code }"
                    placeholder="000000"
                    maxlength="6"
                    style="letter-spacing: 0.5em; font-size: 1.5rem;"
                  />
                  <ErrorMessage name="otp_code" class="text-danger small text-center d-block" />

                  <div v-if="remainingTime > 0" class="form-text text-center">
                    <i class="fa-solid fa-clock me-1"></i>
                    Il codice scade tra <strong>{{ formatTime }}</strong>
                  </div>
                  <div v-else class="form-text text-center text-danger">
                    <i class="fa-solid fa-exclamation-triangle me-1"></i>
                    Codice scaduto. Richiedi un nuovo codice.
                  </div>
                </div>

                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-success btn-lg" :disabled="isLoading || remainingTime === 0">
                    <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                    <i v-else class="fa-solid fa-check-circle me-2"></i>
                    {{ isLoading ? 'Verifica in corso...' : 'Verifica Codice' }}
                  </button>
                  <button type="button" @click="tornaAInserimentoEmail" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-2"></i>
                    Torna Indietro
                  </button>
                </div>
              </Form>

              <div class="text-center mt-4">
                <p class="text-muted small mb-0">
                  Non hai ricevuto il codice?
                  <button @click="tornaAInserimentoEmail" class="btn btn-link btn-sm p-0">
                    Richiedi un nuovo codice
                  </button>
                </p>
              </div>
            </div>

            <!-- STEP 3: Lista Proposte -->
            <div v-if="currentStep === 'show_proposals'">
              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3 mb-md-4">
                <div>
                  <h3 class="mb-1 fs-5 fs-md-4">
                    <i class="fa-solid fa-file-invoice me-2 text-primary"></i>
                    Le tue proposte
                  </h3>
                  <p class="text-muted small mb-0">{{ email }}</p>
                </div>
                <button @click="tornaAllaRicerca" class="btn btn-sm btn-outline-secondary">
                  <i class="fa-solid fa-arrow-left me-1"></i>
                  <span class="d-none d-sm-inline">Cerca con un'altra email</span>
                  <span class="d-sm-none">Altra email</span>
                </button>
              </div>

              <div v-if="proposte.length > 0" class="row g-3 g-md-4">
                <div v-for="proposta in proposte" :key="proposta.id" class="col-12 col-md-6">
                  <div class="card h-100 shadow-sm border-0 hover-card">
                    <div class="card-body p-3 p-md-4">
                      <h5 class="card-title fw-bold text-primary mb-2 mb-md-3 fs-6 fs-md-5">
                        <a :href="`/profilo-medico/${proposta.medico?.id}`" target="_blank" class="text-decoration-none text-primary">
                          {{ proposta.medico?.anagrafica_medico?.ragione_sociale || 'Studio Medico' }}
                          <i class="fa-solid fa-external-link-alt fa-xs ms-1"></i>
                        </a>
                      </h5>
                      <p class="text-muted small mb-2">
                        <i class="fa-solid fa-location-dot me-1 me-md-2"></i>
                        <span class="d-inline-block" style="max-width: calc(100% - 20px)">
                          {{ proposta.medico?.anagrafica_medico?.indirizzo || 'Indirizzo non disponibile' }}
                        </span>
                      </p>
                      <p class="text-muted small mb-2 mb-md-3">
                        <i class="fa-solid fa-calendar me-1 me-md-2"></i>
                        Ricevuta il {{ new Date(proposta.created_at).toLocaleDateString('it-IT') }}
                      </p>
                      <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3 p-3 bg-light rounded">
                        <span class="text-muted small">Prezzo Totale:</span>
                        <span class="fs-5 fs-md-4 fw-bold text-success">
                          € {{ formatCurrency(calcolaTotaleProposta(proposta)) }}
                        </span>
                      </div>
                      <div class="d-grid gap-2">
                        <button @click="apriDettagli(proposta)" class="btn btn-outline-primary btn-sm">
                          <i class="fa-solid fa-eye me-1 me-md-2"></i>
                          <span class="d-none d-sm-inline">Vedi Dettagli</span>
                          <span class="d-sm-none">Dettagli</span>
                        </button>
                        <button @click="apriModalChiamata(proposta)" class="btn btn-success btn-sm">
                          <i class="fa-solid fa-phone me-1 me-md-2"></i>
                          <span class="d-none d-sm-inline">Fissa appuntamento</span>
                          <span class="d-sm-none">Fissa appuntamento</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center p-3 p-sm-4 p-md-5">
                <i class="fa-solid fa-inbox fa-3x fa-md-4x text-muted mb-2 mb-md-3"></i>
                <p class="text-muted mb-0">Nessuna proposta disponibile</p>
              </div>
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
                          <tr v-for="(voce, index) in propostaSelezionata.preventivo_paziente?.json_preventivo?.voci_preventivo" :key="'orig-' + index">
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
            <div v-if="propostaSelezionata" class="alert mt-2 mt-md-3 mb-0 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2"
            :class="(calcolaTotaleOriginale - calcolaTotaleProposta(propostaSelezionata)) >= 0 ? 'alert-success' : 'alert-danger' ">
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

    <!-- MODALE RICHIESTA CHIAMATA -->
    <div v-if="showModalChiamata" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5)">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header py-2 py-md-3">
            <h5 class="modal-title fs-6 fs-md-5">Richiedi una Chiamata</h5>
            <button type="button" class="btn-close" @click="chiudiModalChiamata"></button>
          </div>
          <div class="modal-body p-3 p-md-4">
            <p class="text-muted small mb-3">
              Lascia i tuoi dati e ti contatteremo al più presto per discutere questa proposta e fissare un appuntamento in Studio.
            </p>

            <Form @submit="handleRichiediChiamata" :validation-schema="chiamataSchema" ref="formChiamataRef">
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label class="form-label small">Nome *</label>
                  <Field name="nome" type="text" class="form-control form-control-sm" placeholder="Mario" />
                  <ErrorMessage name="nome" class="text-danger small d-block" />
                </div>

                <div class="col-12 col-md-6">
                  <label class="form-label small">Cognome *</label>
                  <Field name="cognome" type="text" class="form-control form-control-sm" placeholder="Rossi" />
                  <ErrorMessage name="cognome" class="text-danger small d-block" />
                </div>

                <div class="col-12">
                  <label class="form-label small">Cellulare *</label>
                  <Field name="cellulare" type="tel" class="form-control form-control-sm" placeholder="3331234567" />
                  <ErrorMessage name="cellulare" class="text-danger small d-block" />
                </div>
              </div>

              <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4">
                <button type="button" @click="chiudiModalChiamata" class="btn btn-secondary btn-sm order-2 order-sm-1">
                  Annulla
                </button>
                <button type="submit" class="btn btn-success btn-sm order-1 order-sm-2" :disabled="isInvioChiamata">
                  <span v-if="isInvioChiamata" class="spinner-border spinner-border-sm me-2"></span>
                  <span v-if="!isInvioChiamata"><i class="fa-solid fa-phone me-1"></i></span>
                  {{ isInvioChiamata ? 'Invio...' : 'Invia Richiesta' }}
                </button>
              </div>
            </Form>
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

/* Card hover effect */
.hover-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.hover-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
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
.modal.show {
  display: block;
}

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
