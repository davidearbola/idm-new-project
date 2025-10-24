<template>
  <div class="container-fluid py-4">
    <div class="row mb-4">
      <div class="col">
        <h2>Gestione Disponibilità</h2>
        <p class="text-muted">Configura i tuoi orari di disponibilità settimanali per ogni poltrona</p>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary" @click="mostraModalNuova">
          <i class="bi bi-plus-circle me-2"></i>Aggiungi Disponibilità
        </button>
      </div>
    </div>

    <div v-if="disponibilitaStore.isLoading || poltronaStore.isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Caricamento...</span>
      </div>
    </div>

    <div v-else>
      <div v-if="poltronaStore.poltrone.length === 0" class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Non hai ancora creato nessuna poltrona.
        <router-link to="/dashboard/medico/poltrone" class="alert-link">Vai alla gestione poltrone</router-link>
        per creare la prima poltrona.
      </div>

      <div v-else class="card shadow-sm">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead class="table-light">
                <tr>
                  <th class="text-center align-middle" style="width: 150px;">Giorno</th>
                  <th v-for="poltrona in disponibilitaStore.poltrone" :key="poltrona.id" class="text-center align-middle">
                    <i class="bi bi-hospital me-2"></i>{{ poltrona.nome_poltrona }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="giorno in disponibilitaStore.giorniSettimana" :key="giorno.value">
                  <td class="fw-bold text-center align-middle bg-light">
                    {{ giorno.label }}
                  </td>
                  <td v-for="poltrona in disponibilitaStore.poltrone" :key="poltrona.id" class="p-2">
                    <div class="disponibilita-cell">
                      <!-- Mostra le fasce orarie per questo giorno e questa poltrona -->
                      <div
                        v-for="disp in getDisponibilitaPerPoltronaEGiorno(poltrona.id, giorno.value)"
                        :key="disp.id"
                        class="badge bg-primary mb-1 me-1 p-2 disponibilita-badge"
                      >
                        <span>{{ disp.starting_time.substring(0, 5) }} - {{ disp.ending_time.substring(0, 5) }}</span>
                        <button
                          class="btn btn-sm btn-link text-white p-0 ms-2"
                          @click="modificaDisponibilita(disp, poltrona)"
                          title="Modifica"
                        >
                          <i class="bi bi-pencil-fill"></i>
                        </button>
                        <button
                          class="btn btn-sm btn-link text-white p-0 ms-1"
                          @click="confermaEliminazione(disp, poltrona, giorno)"
                          title="Elimina"
                        >
                          <i class="bi bi-trash-fill"></i>
                        </button>
                      </div>

                      <!-- Pulsante per aggiungere una nuova fascia -->
                      <button
                        class="btn btn-sm btn-outline-primary btn-add-slot mt-1"
                        @click="aggiungiDisponibilita(poltrona, giorno)"
                        title="Aggiungi disponibilità"
                      >
                        <i class="bi bi-plus-circle"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-3">
            <small class="text-muted">
              <i class="bi bi-info-circle me-1"></i>
              Gli slot saranno generati automaticamente con intervalli di <strong>30 minuti</strong> per ogni fascia oraria configurata.
            </small>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Aggiungi/Modifica -->
    <div class="modal fade" ref="modalDisponibilita" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ modalMode === 'create' ? 'Nuova Disponibilità' : 'Modifica Disponibilità' }}
            </h5>
            <button type="button" class="btn-close" @click="chiudiModal"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="salvaDisponibilita">
              <div class="mb-3">
                <label class="form-label">Poltrona</label>
                <input
                  v-model="form.nome_poltrona"
                  type="text"
                  class="form-control"
                  readonly
                  disabled
                >
              </div>

              <div class="mb-3">
                <label class="form-label">Giorno della Settimana</label>
                <input
                  v-model="form.nome_giorno"
                  type="text"
                  class="form-control"
                  readonly
                  disabled
                >
              </div>

              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Ora Inizio</label>
                  <input v-model="form.starting_time" type="time" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Ora Fine</label>
                  <input v-model="form.ending_time" type="time" class="form-control" required>
                </div>
              </div>

              <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Gli slot saranno generati automaticamente con intervalli di <strong>30 minuti</strong>
              </div>

              <div v-if="formErrors" class="alert alert-danger">
                <ul class="mb-0">
                  <li v-for="(error, field) in formErrors" :key="field">
                    {{ error[0] }}
                  </li>
                </ul>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="chiudiModal">
              Annulla
            </button>
            <button
              type="button"
              class="btn btn-primary"
              @click="salvaDisponibilita"
              :disabled="disponibilitaStore.isLoading"
            >
              <span v-if="disponibilitaStore.isLoading" class="spinner-border spinner-border-sm me-2"></span>
              {{ modalMode === 'create' ? 'Crea' : 'Aggiorna' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useDisponibilitaStore } from '@/stores/disponibilitaStore'
import { usePoltronaStore } from '@/stores/poltronaStore'
import { Modal } from 'bootstrap'

const disponibilitaStore = useDisponibilitaStore()
const poltronaStore = usePoltronaStore()

const modalDisponibilita = ref(null)
let modalInstance = null
const modalMode = ref('create')
const form = ref({
  id: null,
  poltrona_id: null,
  nome_poltrona: '',
  giorno_settimana: null,
  nome_giorno: '',
  starting_time: '',
  ending_time: '',
})
const formErrors = ref(null)

onMounted(async () => {
  modalInstance = new Modal(modalDisponibilita.value)
  await Promise.all([
    disponibilitaStore.fetchDisponibilita(),
    poltronaStore.fetchPoltrone()
  ])
})

function getDisponibilitaPerPoltronaEGiorno(poltronaId, giornoSettimana) {
  const poltrona = disponibilitaStore.poltrone.find(p => p.id === poltronaId)
  if (!poltrona || !poltrona.disponibilita) return []

  return poltrona.disponibilita
    .filter(disp => disp.giorno_settimana === giornoSettimana)
    .sort((a, b) => a.starting_time.localeCompare(b.starting_time))
}

function aggiungiDisponibilita(poltrona, giorno) {
  modalMode.value = 'create'
  form.value = {
    id: null,
    poltrona_id: poltrona.id,
    nome_poltrona: poltrona.nome_poltrona,
    giorno_settimana: giorno.value,
    nome_giorno: giorno.label,
    starting_time: '08:00',
    ending_time: '12:00',
  }
  formErrors.value = null
  modalInstance.show()
}

function mostraModalNuova() {
  // Se c'è solo una poltrona, preselezionala
  if (disponibilitaStore.poltrone.length === 1) {
    aggiungiDisponibilita(disponibilitaStore.poltrone[0], disponibilitaStore.giorniSettimana[0])
  } else {
    alert('Seleziona una cella nella tabella per aggiungere una disponibilità')
  }
}

function modificaDisponibilita(disp, poltrona) {
  modalMode.value = 'edit'
  const giorno = disponibilitaStore.giorniSettimana.find(g => g.value === disp.giorno_settimana)
  form.value = {
    id: disp.id,
    poltrona_id: poltrona.id,
    nome_poltrona: poltrona.nome_poltrona,
    giorno_settimana: disp.giorno_settimana,
    nome_giorno: giorno ? giorno.label : '',
    starting_time: disp.starting_time.substring(0, 5),
    ending_time: disp.ending_time.substring(0, 5),
  }
  formErrors.value = null
  modalInstance.show()
}

async function salvaDisponibilita() {
  formErrors.value = null

  const data = {
    poltrona_id: form.value.poltrona_id,
    giorno_settimana: form.value.giorno_settimana,
    starting_time: form.value.starting_time,
    ending_time: form.value.ending_time,
  }

  let result
  if (modalMode.value === 'create') {
    result = await disponibilitaStore.creaDisponibilita(data)
  } else {
    result = await disponibilitaStore.aggiornaDisponibilita(form.value.id, data)
  }

  if (result.success) {
    alert(result.message)
    chiudiModal()
  } else {
    if (result.errors) {
      formErrors.value = result.errors
    } else {
      alert(result.message)
    }
  }
}

async function confermaEliminazione(disp, poltrona, giorno) {
  if (confirm(`Sei sicuro di voler eliminare la disponibilità del ${giorno.label} dalle ${disp.starting_time.substring(0, 5)} alle ${disp.ending_time.substring(0, 5)} per ${poltrona.nome_poltrona}?`)) {
    const result = await disponibilitaStore.eliminaDisponibilita(disp.id)
    if (result.success) {
      alert(result.message)
    } else {
      alert(result.message)
    }
  }
}

function chiudiModal() {
  modalInstance.hide()
}
</script>

<style scoped>
.disponibilita-cell {
  min-height: 50px;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 4px;
}

.disponibilita-badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.85rem;
  white-space: nowrap;
}

.disponibilita-badge button {
  font-size: 0.75rem;
  text-decoration: none;
  opacity: 0.8;
  transition: opacity 0.2s;
}

.disponibilita-badge button:hover {
  opacity: 1;
  text-decoration: none;
}

.btn-add-slot {
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
}

.table td {
  vertical-align: top;
}

.table th {
  background-color: #f8f9fa;
  font-weight: 600;
}
</style>
