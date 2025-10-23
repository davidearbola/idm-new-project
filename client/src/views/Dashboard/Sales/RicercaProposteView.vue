<template>
  <div class="container-fluid py-4">
    <div class="row mb-4">
      <div class="col">
        <h2>Ricerca Proposte</h2>
        <p class="text-muted">Cerca le proposte dei pazienti tramite email o cellulare</p>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <form @submit.prevent="cerca">
          <div class="row g-3 align-items-end">
            <div class="col-md-8">
              <label class="form-label">Email o Cellulare del Paziente</label>
              <input 
                v-model="searchQuery" 
                type="text" 
                class="form-control form-control-lg"
                placeholder="Inserisci email o numero di cellulare..."
                required
              >
            </div>
            <div class="col-md-4">
              <button
                type="submit"
                class="btn btn-primary btn-lg w-100"
                :disabled="isLoading"
              >
                <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="fas fa-search me-2"></i>
                Cerca
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div v-if="hasSearched">
      <div v-if="isLoading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Ricerca in corso...</span>
        </div>
      </div>

      <div v-else>
        <div v-if="proposte.length === 0" class="card shadow-sm">
          <div class="card-body text-center py-5">
            <i class="fas fa-search text-muted"></i>
            <p class="mt-3 text-muted">Nessuna proposta trovata per questa ricerca</p>
          </div>
        </div>

        <div v-else>
          <div class="row g-3">
            <div v-for="proposta in proposte" :key="proposta.id" class="col-md-6 col-lg-4">
              <div class="card h-100 shadow-sm">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="card-title mb-0">Proposta #{{ proposta.id }}</h5>
                    <span class="badge bg-success">Accettata</span>
                  </div>

                  <div class="mb-3">
                    <h6 class="text-muted mb-2">
                      <i class="fas fa-user me-2"></i>Paziente
                    </h6>
                    <p class="mb-1">
                      <strong>{{ proposta.preventivo_paziente?.nome_paziente }} {{ proposta.preventivo_paziente?.cognome_paziente || 'N/A' }}</strong>
                    </p>
                    <p class="mb-1 small">
                      <i class="fas fa-envelope me-1"></i>
                      {{ proposta.preventivo_paziente?.email_paziente || 'N/A' }}
                    </p>
                    <p class="mb-0 small" v-if="proposta.preventivo_paziente?.cellulare_paziente">
                      <i class="fas fa-phone me-1"></i>
                      {{ proposta.preventivo_paziente.cellulare_paziente }}
                    </p>
                  </div>

                  <div class="mb-3">
                    <h6 class="text-muted mb-2">
                      <i class="fas fa-hospital me-2"></i>Medico
                    </h6>
                    <p class="mb-1">
                      <strong>{{ proposta.medico?.anagrafica_medico?.ragione_sociale || 'N/A' }}</strong>
                    </p>
                    <p class="mb-0 small" v-if="proposta.medico?.anagrafica_medico?.indirizzo">
                      <i class="fas fa-map-marker-alt me-1"></i>
                      {{ proposta.medico.anagrafica_medico.indirizzo }}
                    </p>
                  </div>

                  <div class="mb-3">
                    <h6 class="text-muted mb-2">
                      <i class="fas fa-euro-sign me-2"></i>Totale Proposta
                    </h6>
                    <h4 class="text-primary mb-0">
                      € {{ formatPrice(proposta.json_proposta?.totale_proposta || 0) }}
                    </h4>
                  </div>

                  <div class="mb-3">
                    <small class="text-muted">
                      <i class="fas fa-calendar me-1"></i>
                      Accettata il {{ formatDate(proposta.updated_at) }}
                    </small>
                  </div>

                  <div class="d-grid gap-2">
                    <button
                      class="btn btn-outline-primary"
                      @click="mostraDettagli(proposta)"
                    >
                      <i class="fas fa-file-invoice me-2"></i>
                      Dettagli Confronto
                    </button>
                    <button
                      class="btn btn-primary"
                      @click="fissaAppuntamento(proposta)"
                    >
                      <i class="fas fa-calendar-check me-2"></i>
                      Fissa Appuntamento
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card shadow-sm">
      <div class="card-body text-center py-5">
        <i class="fas fa-search text-muted"></i>
        <p class="mt-3 text-muted">Effettua una ricerca per visualizzare le proposte</p>
      </div>
    </div>

    <!-- Modale Dettagli Confronto -->
    <div
      class="modal fade"
      id="dettagliModal"
      tabindex="-1"
      aria-labelledby="dettagliModalLabel"
      aria-hidden="true"
      ref="dettagliModalRef"
    >
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="dettagliModalLabel">
              <i class="fas fa-balance-scale me-2"></i>
              Confronto Preventivo vs Proposta
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" v-if="propostaSelezionata">
            <!-- Riepilogo Totali -->
            <div class="row mb-4">
              <div class="col-md-5">
                <div class="card bg-light">
                  <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Preventivo Originale</h6>
                    <h3 class="text-danger mb-0">
                      € {{ formatPrice(propostaSelezionata.preventivo_paziente?.json_preventivo?.totale_preventivo || 0) }}
                    </h3>
                  </div>
                </div>
              </div>
              <div class="col-md-2 d-flex align-items-center justify-content-center">
                <i class="fas fa-arrow-right fa-2x text-primary"></i>
              </div>
              <div class="col-md-5">
                <div class="card bg-success bg-opacity-10">
                  <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Proposta Medico</h6>
                    <h3 class="text-success mb-0">
                      € {{ formatPrice(propostaSelezionata.json_proposta?.totale_proposta || 0) }}
                    </h3>
                  </div>
                </div>
              </div>
            </div>

            <!-- Risparmio -->
            <div class="alert alert-success text-center mb-4" v-if="calcolaRisparmio(propostaSelezionata) > 0">
              <h5 class="mb-0">
                <i class="fas fa-piggy-bank me-2"></i>
                Risparmio: € {{ formatPrice(calcolaRisparmio(propostaSelezionata)) }}
                ({{ calcolaPercentualeRisparmio(propostaSelezionata) }}%)
              </h5>
            </div>

            <!-- Tabella Confronto Prestazioni -->
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Prestazione</th>
                    <th class="text-center">Quantità</th>
                    <th class="text-end">Prezzo Preventivo</th>
                    <th class="text-end">Prezzo Proposta</th>
                    <th class="text-end">Differenza</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(voce, index) in propostaSelezionata.json_proposta?.voci_proposta" :key="index">
                    <td>
                      <strong>{{ voce.prestazione_corrispondente }}</strong>
                      <br>
                      <small class="text-muted">{{ voce.prestazione_originale }}</small>
                    </td>
                    <td class="text-center">{{ voce.quantità }}</td>
                    <td class="text-end">
                      € {{ formatPrice(getPrezzoOriginale(propostaSelezionata, voce.prestazione_originale)) }}
                    </td>
                    <td class="text-end text-success">
                      <strong>€ {{ formatPrice(voce.prezzo) }}</strong>
                    </td>
                    <td class="text-end">
                      <span :class="getDifferenzaClass(voce.prezzo, getPrezzoOriginale(propostaSelezionata, voce.prestazione_originale))">
                        {{ formatDifferenza(voce.prezzo, getPrezzoOriginale(propostaSelezionata, voce.prestazione_originale)) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <th colspan="2">TOTALE</th>
                    <th class="text-end">
                      € {{ formatPrice(propostaSelezionata.preventivo_paziente?.json_preventivo?.totale_preventivo || 0) }}
                    </th>
                    <th class="text-end text-success">
                      € {{ formatPrice(propostaSelezionata.json_proposta?.totale_proposta || 0) }}
                    </th>
                    <th class="text-end">
                      <strong :class="getDifferenzaClass(
                        propostaSelezionata.json_proposta?.totale_proposta,
                        propostaSelezionata.preventivo_paziente?.json_preventivo?.totale_preventivo
                      )">
                        {{ formatDifferenza(
                          propostaSelezionata.json_proposta?.totale_proposta,
                          propostaSelezionata.preventivo_paziente?.json_preventivo?.totale_preventivo
                        ) }}
                      </strong>
                    </th>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Info Paziente e Medico -->
            <div class="row mt-4">
              <div class="col-md-6">
                <div class="card">
                  <div class="card-body">
                    <h6 class="card-title">
                      <i class="fas fa-user me-2"></i>Paziente
                    </h6>
                    <p class="mb-1">
                      <strong>{{ propostaSelezionata.preventivo_paziente?.nome_paziente }} {{ propostaSelezionata.preventivo_paziente?.cognome_paziente }}</strong>
                    </p>
                    <p class="mb-1 small">
                      <i class="fas fa-envelope me-1"></i>
                      {{ propostaSelezionata.preventivo_paziente?.email_paziente }}
                    </p>
                    <p class="mb-0 small" v-if="propostaSelezionata.preventivo_paziente?.cellulare_paziente">
                      <i class="fas fa-phone me-1"></i>
                      {{ propostaSelezionata.preventivo_paziente.cellulare_paziente }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card">
                  <div class="card-body">
                    <h6 class="card-title">
                      <i class="fas fa-hospital me-2"></i>Medico
                    </h6>
                    <p class="mb-1">
                      <strong>{{ propostaSelezionata.medico?.anagrafica_medico?.ragione_sociale }}</strong>
                    </p>
                    <p class="mb-0 small" v-if="propostaSelezionata.medico?.anagrafica_medico?.indirizzo">
                      <i class="fas fa-map-marker-alt me-1"></i>
                      {{ propostaSelezionata.medico.anagrafica_medico.indirizzo }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            <button
              type="button"
              class="btn btn-primary"
              @click="fissaAppuntamentoDaModale"
            >
              <i class="fas fa-calendar-check me-2"></i>
              Fissa Appuntamento
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
import { useSalesStore } from '@/stores/salesStore'
import { useToast } from 'vue-toastification'
import { useRouter } from 'vue-router'
import { Modal } from 'bootstrap'

const salesStore = useSalesStore()
const { isLoading, proposte } = storeToRefs(salesStore)
const toast = useToast()
const router = useRouter()

const searchQuery = ref('')
const hasSearched = ref(false)
const propostaSelezionata = ref(null)
const dettagliModalRef = ref(null)
const dettagliModalInstance = ref(null)

onMounted(() => {
  if (dettagliModalRef.value) {
    dettagliModalInstance.value = new Modal(dettagliModalRef.value)
  }
})

async function cerca() {
  if (!searchQuery.value.trim()) {
    toast.warning('Inserisci un termine di ricerca')
    return
  }

  hasSearched.value = true
  const result = await salesStore.cercaProposte(searchQuery.value.trim())

  if (!result.success) {
    toast.error(result.message)
  } else if (result.data.length === 0) {
    toast.info('Nessuna proposta trovata')
  }
}

function fissaAppuntamento(proposta) {
  salesStore.setPropostaSelezionata(proposta)
  router.push({
    name: 'sales-agenda-medico',
    params: { medicoId: proposta.medico_user_id }
  })
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString('it-IT', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

function formatPrice(price) {
  return new Intl.NumberFormat('it-IT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(price)
}

function mostraDettagli(proposta) {
  propostaSelezionata.value = proposta
  if (dettagliModalInstance.value) {
    dettagliModalInstance.value.show()
  }
}

function getPrezzoOriginale(proposta, prestazioneOriginale) {
  const vociPreventivo = proposta.preventivo_paziente?.json_preventivo?.voci_preventivo || []
  const voce = vociPreventivo.find(v => v.prestazione === prestazioneOriginale)
  return voce ? voce.prezzo : 0
}

function calcolaRisparmio(proposta) {
  const totalePreventivo = proposta.preventivo_paziente?.json_preventivo?.totale_preventivo || 0
  const totaleProposta = proposta.json_proposta?.totale_proposta || 0
  return totalePreventivo - totaleProposta
}

function calcolaPercentualeRisparmio(proposta) {
  const totalePreventivo = proposta.preventivo_paziente?.json_preventivo?.totale_preventivo || 0
  const risparmio = calcolaRisparmio(proposta)
  if (totalePreventivo === 0) return 0
  return ((risparmio / totalePreventivo) * 100).toFixed(1)
}

function formatDifferenza(prezzoProposta, prezzoOriginale) {
  const diff = prezzoOriginale - prezzoProposta
  if (diff > 0) {
    return `-€ ${formatPrice(diff)}`
  } else if (diff < 0) {
    return `+€ ${formatPrice(Math.abs(diff))}`
  }
  return '€ 0,00'
}

function getDifferenzaClass(prezzoProposta, prezzoOriginale) {
  const diff = prezzoOriginale - prezzoProposta
  if (diff > 0) {
    return 'text-success fw-bold'
  } else if (diff < 0) {
    return 'text-danger fw-bold'
  }
  return 'text-muted'
}

function fissaAppuntamentoDaModale() {
  if (dettagliModalInstance.value) {
    dettagliModalInstance.value.hide()
  }
  fissaAppuntamento(propostaSelezionata.value)
}
</script>

<style scoped>
.card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
}

.card-title {
  color: #2c3e50;
}
</style>