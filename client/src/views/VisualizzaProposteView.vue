<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { Form, Field, ErrorMessage } from 'vee-validate'
import * as yup from 'yup'
import { useToast } from 'vue-toastification'
import { useTelmarStore } from '@/stores/telmarStore'
import axios from 'axios'
import logoSrc from '@/assets/images/logo-IDM.png'

const route = useRoute()
const toast = useToast()
const telmarStore = useTelmarStore()

const email = ref('')
const proposte = ref([])
const preventivi = ref([])
const isLoading = ref(false)
const showEmailForm = ref(true)
const showModalDettagli = ref(false)
const propostaSelezionata = ref(null)
const showModalChiamata = ref(false)
const propostaPerChiamata = ref(null)
const isInvioChiamata = ref(false)
const formChiamataRef = ref(null)

// Validation schema per la chiamata
const chiamataSchema = yup.object({
  nome: yup.string().required('Il nome è obbligatorio').max(255),
  cognome: yup.string().required('Il cognome è obbligatorio').max(255),
  cellulare: yup.string().required('Il cellulare è obbligatorio').min(9, 'Il cellulare deve essere di almeno 9 cifre').max(20),
})

// Se l'email è nel query param, carica subito le proposte
onMounted(() => {
  if (route.query.email) {
    email.value = route.query.email
    recuperaProposte()
  }
})

const recuperaProposte = async () => {
  if (!email.value) {
    toast.error('Inserisci una email valida')
    return
  }

  isLoading.value = true

  try {
    const response = await axios.post('/api/preventivi/recupera-proposte', {
      email: email.value
    })

    if (response.data.success) {
      proposte.value = response.data.proposte
      preventivi.value = response.data.preventivi
      showEmailForm.value = false
      toast.success('Proposte caricate con successo!')
    }
  } catch (error) {
    if (error.response?.status === 404) {
      toast.error('Nessuna proposta trovata per questa email')
    } else {
      toast.error('Errore durante il caricamento delle proposte')
    }
  } finally {
    isLoading.value = false
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

const tornaAllaRicerca = () => {
  showEmailForm.value = true
  proposte.value = []
  preventivi.value = []
  email.value = ''
}

// --- MODALE RICHIESTA CHIAMATA ---
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

            <!-- FORM EMAIL -->
            <div v-if="showEmailForm">
              <h3 class="mb-2 mb-md-3 fs-4 fs-md-3">Inserisci la tua email</h3>
              <p class="text-muted small mb-3 mb-md-4">
                Inserisci l'email che hai usato per caricare il preventivo per visualizzare le proposte ricevute.
              </p>

              <form @submit.prevent="recuperaProposte">
                <div class="mb-3">
                  <label class="form-label small">Email</label>
                  <input
                    type="email"
                    class="form-control form-control-sm"
                    v-model="email"
                    placeholder="la-tua-email@esempio.it"
                    required
                  />
                </div>

                <div class="d-grid d-sm-flex justify-content-sm-end">
                  <button type="submit" class="btn btn-primary btn-lg" :disabled="isLoading">
                    <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                    <span class="d-none d-sm-inline">{{ isLoading ? 'Caricamento...' : 'Visualizza Proposte' }}</span>
                    <span class="d-sm-none">{{ isLoading ? 'Caricamento...' : 'Visualizza' }}</span>
                  </button>
                </div>
              </form>
            </div>

            <!-- LISTA PROPOSTE -->
            <div v-else>
              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3 mb-md-4">
                <h3 class="mb-0 fs-5 fs-md-4">Proposte per {{ email }}</h3>
                <button @click="tornaAllaRicerca" class="btn btn-sm btn-outline-secondary">
                  <span class="d-none d-sm-inline">Cerca con un'altra email</span>
                  <span class="d-sm-none">Altra email</span>
                </button>
              </div>

              <div class="row g-3 g-md-4">
                <div v-for="proposta in proposte" :key="proposta.id" class="col-12 col-md-6">
                  <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-3 p-md-4">
                      <h5 class="card-title fw-bold text-primary mb-2 mb-md-3 fs-6 fs-md-5">
                        {{ proposta.medico?.anagrafica_medico?.ragione_sociale || 'Studio Medico' }}
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
                      <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
                        <span class="text-muted small">Prezzo Totale:</span>
                        <span class="fs-5 fs-md-4 fw-bold text-success">
                          € {{ formatCurrency(calcolaTotaleProposta(proposta)) }}
                        </span>
                      </div>
                      <div class="d-grid gap-2">
                        <button @click="apriDettagli(proposta)" class="btn btn-outline-primary btn-sm">
                          <i class="fa-solid fa-eye me-1 me-md-2"></i><span class="d-none d-sm-inline">Vedi Dettagli</span><span class="d-sm-none">Dettagli</span>
                        </button>
                        <button @click="apriModalChiamata(proposta)" class="btn btn-success btn-sm">
                          <i class="fa-solid fa-phone me-1 me-md-2"></i><span class="d-none d-sm-inline">Richiedi Chiamata</span><span class="d-sm-none">Chiamami</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="proposte.length === 0" class="text-center p-3 p-sm-4 p-md-5">
                <i class="fa-solid fa-inbox fa-3x fa-md-4x text-muted mb-2 mb-md-3"></i>
                <p class="text-muted small mb-0">Nessuna proposta disponibile</p>
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
              Lascia i tuoi dati e ti contatteremo al più presto per discutere questa proposta.
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
