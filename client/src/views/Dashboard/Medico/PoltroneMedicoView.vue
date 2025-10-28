<template>
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2>Gestione Poltrone e Disponibilità</h2>
        <p class="text-muted">Configura le tue poltrone e i relativi orari di disponibilità settimanali</p>
      </div>
      <button class="btn btn-primary" @click="apriModalNuovaPoltrona">
        <i class="fas fa-plus-circle me-2"></i>Aggiungi Poltrona
      </button>
    </div>

    <div v-if="disponibilitaStore.isLoading || poltronaStore.isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Caricamento...</span>
      </div>
    </div>

    <div v-else-if="disponibilitaStore.poltrone.length === 0" class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>
      Non hai ancora creato nessuna poltrona. Clicca su "Aggiungi Poltrona" per iniziare.
    </div>

    <div v-else>
      <!-- Tabella per ogni poltrona -->
      <div v-for="poltrona in disponibilitaStore.poltrone" :key="poltrona.id" class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="fas fa-teeth me-2"></i>{{ poltrona.nome_poltrona }}
          </h5>
          <div class="btn-group">
            <button class="btn btn-sm btn-outline-primary" @click="apriModalModificaPoltrona(poltrona)">
              <i class="fas fa-edit me-1"></i>Modifica Nome
            </button>
            <button class="btn btn-sm btn-outline-danger" @click="confermaEliminaPoltrona(poltrona)">
              <i class="fas fa-trash me-1"></i>Elimina Poltrona
            </button>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th v-for="giorno in disponibilitaStore.giorniSettimana" :key="giorno.value" class="text-center align-middle giorno-header">
                    {{ giorno.label }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td v-for="giorno in disponibilitaStore.giorniSettimana" :key="giorno.value" class="p-2 disponibilita-cell">
                    <div class="cell-content">
                      <!-- Mostra le fasce orarie per questo giorno -->
                      <div
                        v-for="disp in getDisponibilitaPerPoltronaEGiorno(poltrona.id, giorno.value)"
                        :key="disp.id"
                        class="badge bg-primary mb-1 p-2 disponibilita-badge"
                        @click="modificaDisponibilita(disp, poltrona, giorno)"
                        role="button"
                      >
                        <span>{{ disp.starting_time.substring(0, 5) }} - {{ disp.ending_time.substring(0, 5) }}</span>
                        <button
                          class="btn btn-sm btn-link text-white p-0 ms-2"
                          @click.stop="confermaEliminaDisponibilita(disp, poltrona, giorno)"
                          title="Elimina"
                        >
                          <i class="fas fa-times-circle"></i>
                        </button>
                      </div>

                      <!-- Pulsante per aggiungere una nuova fascia -->
                      <button
                        class="btn btn-sm btn-outline-primary btn-add-slot w-100 mt-1"
                        @click="aggiungiDisponibilita(poltrona, giorno)"
                        title="Aggiungi disponibilità"
                      >
                        <i class="fas fa-plus-circle"></i> Aggiungi fascia
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer text-muted">
          <small>
            <i class="fas fa-info-circle me-1"></i>
            Gli slot saranno generati automaticamente con intervalli di <strong>30 minuti</strong> per ogni fascia oraria configurata.
          </small>
        </div>
      </div>
    </div>

    <!-- Modal Crea/Modifica Poltrona -->
    <div class="modal fade" id="modalPoltrona" tabindex="-1" ref="modalPoltrona">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ modalPoltronaModifica ? 'Modifica Poltrona' : 'Nuova Poltrona' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="salvaPoltrona">
              <div class="mb-3">
                <label for="nomePoltrona" class="form-label">Nome Poltrona</label>
                <input
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': erroriPoltrona.nome_poltrona }"
                  id="nomePoltrona"
                  v-model="formPoltrona.nome_poltrona"
                  placeholder="es. Poltrona 1"
                  required
                >
                <div v-if="erroriPoltrona.nome_poltrona" class="invalid-feedback">
                  {{ erroriPoltrona.nome_poltrona[0] }}
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
            <button type="button" class="btn btn-primary" @click="salvaPoltrona" :disabled="salvataggioInCorso">
              <span v-if="salvataggioInCorso" class="spinner-border spinner-border-sm me-2"></span>
              {{ modalPoltronaModifica ? 'Salva Modifiche' : 'Crea Poltrona' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Aggiungi/Modifica Disponibilità -->
    <div class="modal fade" ref="modalDisponibilita" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ modalDisponibilitaModifica ? 'Modifica Disponibilità' : 'Nuova Disponibilità' }}
            </h5>
            <button type="button" class="btn-close" @click="chiudiModalDisponibilita"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="salvaDisponibilita">
              <div class="mb-3">
                <label class="form-label">Poltrona</label>
                <input
                  v-model="formDisponibilita.nome_poltrona"
                  type="text"
                  class="form-control"
                  readonly
                  disabled
                >
              </div>

              <div class="mb-3">
                <label class="form-label">Giorno della Settimana</label>
                <input
                  v-model="formDisponibilita.nome_giorno"
                  type="text"
                  class="form-control"
                  readonly
                  disabled
                >
              </div>

              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Ora Inizio</label>
                  <input v-model="formDisponibilita.starting_time" type="time" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Ora Fine</label>
                  <input v-model="formDisponibilita.ending_time" type="time" class="form-control" required>
                </div>
              </div>

              <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Gli slot saranno generati automaticamente con intervalli di <strong>30 minuti</strong>
              </div>

              <div v-if="erroriDisponibilita" class="alert alert-danger">
                <ul class="mb-0">
                  <li v-for="(error, field) in erroriDisponibilita" :key="field">
                    {{ error[0] }}
                  </li>
                </ul>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="chiudiModalDisponibilita">
              Annulla
            </button>
            <button
              type="button"
              class="btn btn-primary"
              @click="salvaDisponibilita"
              :disabled="disponibilitaStore.isLoading"
            >
              <span v-if="disponibilitaStore.isLoading" class="spinner-border spinner-border-sm me-2"></span>
              {{ modalDisponibilitaModifica ? 'Salva' : 'Crea' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Conferma Eliminazione Poltrona -->
    <div class="modal fade" id="modalConfermaEliminazionePoltrona" tabindex="-1" ref="modalConfermaEliminazionePoltrona">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">Conferma Eliminazione Poltrona</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Sei sicuro di voler eliminare la poltrona <strong>{{ poltronaDaEliminare?.nome_poltrona }}</strong>?</p>
            <p class="text-danger mb-0">
              <i class="fas fa-exclamation-triangle me-2"></i>
              Questa azione eliminerà anche tutte le disponibilità associate a questa poltrona.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
            <button type="button" class="btn btn-danger" @click="eliminaPoltrona" :disabled="eliminazioneInCorso">
              <span v-if="eliminazioneInCorso" class="spinner-border spinner-border-sm me-2"></span>
              Elimina
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { usePoltronaStore } from '@/stores/poltronaStore'
import { useDisponibilitaStore } from '@/stores/disponibilitaStore'
import { useToast } from 'vue-toastification'
import { Modal } from 'bootstrap'

const poltronaStore = usePoltronaStore()
const disponibilitaStore = useDisponibilitaStore()
const toast = useToast()

// Modal Poltrona
const modalPoltrona = ref(null)
const modalPoltronaModifica = ref(false)
const salvataggioInCorso = ref(false)
const formPoltrona = ref({
  id: null,
  nome_poltrona: ''
})
const erroriPoltrona = ref({})

// Modal Disponibilità
const modalDisponibilita = ref(null)
const modalDisponibilitaModifica = ref(false)
const formDisponibilita = ref({
  id: null,
  poltrona_id: null,
  nome_poltrona: '',
  giorno_settimana: null,
  nome_giorno: '',
  starting_time: '',
  ending_time: '',
})
const erroriDisponibilita = ref(null)

// Modal Conferma Eliminazione Poltrona
const modalConfermaEliminazionePoltrona = ref(null)
const eliminazioneInCorso = ref(false)
const poltronaDaEliminare = ref(null)

let modalPoltronaInstance = null
let modalDisponibilitaInstance = null
let modalConfermaEliminazionePoltronaInstance = null

onMounted(async () => {
  await Promise.all([
    disponibilitaStore.fetchDisponibilita(),
    poltronaStore.fetchPoltrone()
  ])

  modalPoltronaInstance = new Modal(modalPoltrona.value)
  modalDisponibilitaInstance = new Modal(modalDisponibilita.value)
  modalConfermaEliminazionePoltronaInstance = new Modal(modalConfermaEliminazionePoltrona.value)
})

// ========== GESTIONE POLTRONE ==========

function apriModalNuovaPoltrona() {
  modalPoltronaModifica.value = false
  formPoltrona.value = {
    id: null,
    nome_poltrona: ''
  }
  erroriPoltrona.value = {}
  modalPoltronaInstance.show()
}

function apriModalModificaPoltrona(poltrona) {
  modalPoltronaModifica.value = true
  formPoltrona.value = {
    id: poltrona.id,
    nome_poltrona: poltrona.nome_poltrona
  }
  erroriPoltrona.value = {}
  modalPoltronaInstance.show()
}

async function salvaPoltrona() {
  salvataggioInCorso.value = true
  erroriPoltrona.value = {}

  try {
    let result
    if (modalPoltronaModifica.value) {
      result = await poltronaStore.aggiornaPoltrona(formPoltrona.value.id, {
        nome_poltrona: formPoltrona.value.nome_poltrona
      })
    } else {
      result = await poltronaStore.creaPoltrona({
        nome_poltrona: formPoltrona.value.nome_poltrona
      })
    }

    if (result.success) {
      modalPoltronaInstance.hide()
      toast.success(result.message)
      // Ricarica le disponibilità per avere i dati aggiornati con la nuova poltrona
      await disponibilitaStore.fetchDisponibilita()
    } else {
      if (result.errors) {
        erroriPoltrona.value = result.errors
      } else {
        toast.error(result.message)
      }
    }
  } catch (error) {
    console.error('Errore nel salvataggio:', error)
    toast.error('Si è verificato un errore durante il salvataggio')
  } finally {
    salvataggioInCorso.value = false
  }
}

function confermaEliminaPoltrona(poltrona) {
  poltronaDaEliminare.value = poltrona
  modalConfermaEliminazionePoltronaInstance.show()
}

async function eliminaPoltrona() {
  eliminazioneInCorso.value = true

  try {
    const result = await poltronaStore.eliminaPoltrona(poltronaDaEliminare.value.id)

    if (result.success) {
      modalConfermaEliminazionePoltronaInstance.hide()
      toast.success(result.message)
      // Ricarica le disponibilità
      await disponibilitaStore.fetchDisponibilita()
    } else {
      toast.error(result.message)
    }
  } catch (error) {
    console.error('Errore nell\'eliminazione:', error)
    toast.error('Si è verificato un errore durante l\'eliminazione')
  } finally {
    eliminazioneInCorso.value = false
  }
}

// ========== GESTIONE DISPONIBILITÀ ==========

function getDisponibilitaPerPoltronaEGiorno(poltronaId, giornoSettimana) {
  const poltrona = disponibilitaStore.poltrone.find(p => p.id === poltronaId)
  if (!poltrona || !poltrona.disponibilita) return []

  return poltrona.disponibilita
    .filter(disp => disp.giorno_settimana === giornoSettimana)
    .sort((a, b) => a.starting_time.localeCompare(b.starting_time))
}

function aggiungiDisponibilita(poltrona, giorno) {
  modalDisponibilitaModifica.value = false
  formDisponibilita.value = {
    id: null,
    poltrona_id: poltrona.id,
    nome_poltrona: poltrona.nome_poltrona,
    giorno_settimana: giorno.value,
    nome_giorno: giorno.label,
    starting_time: '08:00',
    ending_time: '12:00',
  }
  erroriDisponibilita.value = null
  modalDisponibilitaInstance.show()
}

function modificaDisponibilita(disp, poltrona, giorno) {
  modalDisponibilitaModifica.value = true
  formDisponibilita.value = {
    id: disp.id,
    poltrona_id: poltrona.id,
    nome_poltrona: poltrona.nome_poltrona,
    giorno_settimana: disp.giorno_settimana,
    nome_giorno: giorno.label,
    starting_time: disp.starting_time.substring(0, 5),
    ending_time: disp.ending_time.substring(0, 5),
  }
  erroriDisponibilita.value = null
  modalDisponibilitaInstance.show()
}

async function salvaDisponibilita() {
  erroriDisponibilita.value = null

  const data = {
    poltrona_id: formDisponibilita.value.poltrona_id,
    giorno_settimana: formDisponibilita.value.giorno_settimana,
    starting_time: formDisponibilita.value.starting_time,
    ending_time: formDisponibilita.value.ending_time,
  }

  let result
  if (modalDisponibilitaModifica.value) {
    result = await disponibilitaStore.aggiornaDisponibilita(formDisponibilita.value.id, data)
  } else {
    result = await disponibilitaStore.creaDisponibilita(data)
  }

  if (result.success) {
    toast.success(result.message)
    chiudiModalDisponibilita()
  } else {
    if (result.errors) {
      erroriDisponibilita.value = result.errors
    } else {
      toast.error(result.message)
    }
  }
}

async function confermaEliminaDisponibilita(disp, poltrona, giorno) {
  if (confirm(`Sei sicuro di voler eliminare la disponibilità del ${giorno.label} dalle ${disp.starting_time.substring(0, 5)} alle ${disp.ending_time.substring(0, 5)} per ${poltrona.nome_poltrona}?`)) {
    const result = await disponibilitaStore.eliminaDisponibilita(disp.id)
    if (result.success) {
      toast.success(result.message)
    } else {
      toast.error(result.message)
    }
  }
}

function chiudiModalDisponibilita() {
  modalDisponibilitaInstance.hide()
}
</script>

<style scoped>
.giorno-header {
  background-color: #f8f9fa;
  font-weight: 600;
  min-width: 150px;
}

.disponibilita-cell {
  vertical-align: top;
  min-height: 120px;
}

.cell-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.disponibilita-badge {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.85rem;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.2s;
  width: 100%;
}

.disponibilita-badge:hover {
  transform: translateY(-2px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.disponibilita-badge button {
  font-size: 0.9rem;
  text-decoration: none;
  opacity: 0.8;
  transition: opacity 0.2s;
}

.disponibilita-badge button:hover {
  opacity: 1;
  text-decoration: none;
}

.btn-add-slot {
  font-size: 0.85rem;
  padding: 0.5rem;
}

.table td {
  vertical-align: top;
}

.table th {
  background-color: #f8f9fa;
  font-weight: 600;
}
</style>
