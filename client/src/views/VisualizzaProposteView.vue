<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import axios from 'axios'
import logoSrc from '@/assets/images/logo-IDM.png'

const route = useRoute()
const toast = useToast()

const email = ref('')
const proposte = ref([])
const preventivi = ref([])
const isLoading = ref(false)
const showEmailForm = ref(true)
const showModalDettagli = ref(false)
const propostaSelezionata = ref(null)

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
</script>

<template>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <!-- Logo -->
        <div class="text-center mb-4">
          <RouterLink to="/">
            <img :src="logoSrc" alt="Il Dentista Migliore Logo" style="height: 3rem;">
          </RouterLink>
        </div>

        <h1 class="display-5 fw-bold text-center mb-3">Le Tue Proposte</h1>
        <p class="lead text-muted text-center mb-5">
          Visualizza e confronta le proposte ricevute dagli studi medici
        </p>

        <div class="card border-0 shadow-sm">
          <div class="card-body p-4 p-md-5">

            <!-- FORM EMAIL -->
            <div v-if="showEmailForm">
              <h3 class="mb-3">Inserisci la tua email</h3>
              <p class="text-muted">
                Inserisci l'email che hai usato per caricare il preventivo per visualizzare le proposte ricevute.
              </p>

              <form @submit.prevent="recuperaProposte">
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input
                    type="email"
                    class="form-control"
                    v-model="email"
                    placeholder="la-tua-email@esempio.it"
                    required
                  />
                </div>

                <div class="text-end">
                  <button type="submit" class="btn btn-primary btn-lg" :disabled="isLoading">
                    <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                    {{ isLoading ? 'Caricamento...' : 'Visualizza Proposte' }}
                  </button>
                </div>
              </form>
            </div>

            <!-- LISTA PROPOSTE -->
            <div v-else>
              <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0">Proposte per {{ email }}</h3>
                <button @click="tornaAllaRicerca" class="btn btn-sm btn-outline-secondary">
                  Cerca con un'altra email
                </button>
              </div>

              <div class="row g-4">
                <div v-for="proposta in proposte" :key="proposta.id" class="col-md-6">
                  <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                      <h5 class="card-title fw-bold text-primary mb-3">
                        {{ proposta.medico?.anagrafica_medico?.ragione_sociale || 'Studio Medico' }}
                      </h5>
                      <p class="text-muted small mb-2">
                        <i class="fa-solid fa-location-dot me-2"></i>
                        {{ proposta.medico?.anagrafica_medico?.indirizzo || 'Indirizzo non disponibile' }}
                      </p>
                      <p class="text-muted small mb-3">
                        <i class="fa-solid fa-calendar me-2"></i>
                        Ricevuta il {{ new Date(proposta.created_at).toLocaleDateString('it-IT') }}
                      </p>
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Prezzo Totale:</span>
                        <span class="fs-4 fw-bold text-success">
                          € {{ formatCurrency(calcolaTotaleProposta(proposta)) }}
                        </span>
                      </div>
                      <button @click="apriDettagli(proposta)" class="btn btn-outline-primary w-100">
                        <i class="fa-solid fa-eye me-2"></i>Vedi Dettagli
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="proposte.length === 0" class="text-center p-5">
                <i class="fa-solid fa-inbox fa-4x text-muted mb-3"></i>
                <p class="text-muted">Nessuna proposta disponibile</p>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- MODALE CONFRONTO PREVENTIVO-PROPOSTA -->
    <div v-if="showModalDettagli" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5)">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confronto Preventivo - Proposta</h5>
            <button type="button" class="btn-close" @click="chiudiDettagli"></button>
          </div>
          <div class="modal-body">
            <div v-if="propostaSelezionata" class="row">
              <!-- Preventivo Originale -->
              <div class="col-md-6">
                <h5 class="fw-bold text-primary mb-3">
                  <i class="fa-solid fa-file-invoice me-2"></i>Preventivo Originale
                </h5>
                <div class="card bg-light">
                  <div class="card-body">
                    <table class="table table-sm">
                      <thead>
                        <tr>
                          <th>Prestazione</th>
                          <th>Qnt</th>
                          <th class="text-end">Prezzo</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(voce, index) in propostaSelezionata.preventivo_paziente?.json_preventivo?.voci_preventivo" :key="'orig-' + index">
                          <td>{{ voce.prestazione }}</td>
                          <td>{{ voce.quantità }}</td>
                          <td class="text-end">€ {{ formatCurrency(voce.prezzo) }}</td>
                        </tr>
                      </tbody>
                      <tfoot>
                        <tr class="fw-bold">
                          <td colspan="2">Totale</td>
                          <td class="text-end">€ {{ formatCurrency(calcolaTotaleOriginale) }}</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>

              <!-- Proposta del Medico -->
              <div class="col-md-6">
                <h5 class="fw-bold text-success mb-3">
                  <i class="fa-solid fa-handshake me-2"></i>Proposta - {{ propostaSelezionata.medico?.anagrafica_medico?.ragione_sociale }}
                </h5>
                <div class="card bg-light">
                  <div class="card-body">
                    <table class="table table-sm">
                      <thead>
                        <tr>
                          <th>Prestazione</th>
                          <th>Qnt</th>
                          <th class="text-end">Prezzo</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(voce, index) in propostaSelezionata.json_proposta?.voci_proposta" :key="'prop-' + index">
                          <td>
                            <div>{{ voce.prestazione_corrispondente || voce.prestazione_originale }}</div>
                            <small v-if="voce.prestazione_corrispondente !== voce.prestazione_originale" class="text-muted">
                              (era: {{ voce.prestazione_originale }})
                            </small>
                          </td>
                          <td>{{ voce.quantità }}</td>
                          <td class="text-end">€ {{ formatCurrency(voce.prezzo) }}</td>
                        </tr>
                      </tbody>
                      <tfoot>
                        <tr class="fw-bold">
                          <td colspan="2">Totale</td>
                          <td class="text-end text-success">
                            € {{ formatCurrency(calcolaTotaleProposta(propostaSelezionata)) }}
                          </td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Risparmio -->
            <div v-if="propostaSelezionata" class="alert mt-4 d-flex justify-content-between align-items-center" 
            :class="formatCurrency(calcolaTotaleOriginale - calcolaTotaleProposta(propostaSelezionata)) >= 0 ? 'alert-info' : 'alert-danger' ">
              <span class="fw-bold">Risparmio</span>
              <span class="fs-5 fw-bold">
                € {{ formatCurrency(calcolaTotaleOriginale - calcolaTotaleProposta(propostaSelezionata)) }}
              </span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="chiudiDettagli">Chiudi</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal.show {
  display: block;
}
</style>
