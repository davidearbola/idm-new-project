<script setup>
import { RouterLink } from 'vue-router'
import logoSrc from '@/assets/images/logo-IDM.png'
</script>

<template>
  <div class="not-found-container d-flex align-items-center justify-content-center min-vh-100">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-8 text-center">
          <!-- Logo -->
          <div class="mb-4">
            <RouterLink to="/">
              <img :src="logoSrc" alt="Il Dentista Migliore Logo" class="logo-404" />
            </RouterLink>
          </div>

          <!-- 404 Text -->
          <h1 class="display-1 fw-bold text-primary mb-3">404</h1>
          <h2 class="h3 mb-4">Oops! Questa pagina si è cariata</h2>
          <p class="lead text-muted mb-5">
            La pagina che stai cercando non esiste o è stata spostata.<br>
            Non preoccuparti, possiamo aiutarti a ritrovare la strada giusta!
          </p>

          <!-- Action Buttons -->
          <div class="d-flex gap-3 justify-content-center flex-wrap">
            <RouterLink to="/" class="btn btn-primary btn-lg px-5">
              <i class="fas fa-home me-2"></i>
              Torna alla Home
            </RouterLink>
            <RouterLink to="/carica-preventivo" class="btn btn-accent btn-lg px-5">
              <i class="fas fa-file-upload me-2"></i>
              Carica Preventivo
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.not-found-container {
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
  padding: 2rem 1rem;
}

.logo-404 {
  height: 4rem;
  transition: transform 0.3s ease;
}

.logo-404:hover {
  transform: scale(1.05);
}

.tooth-icon-wrapper {
  animation: float 3s ease-in-out infinite;
}

.tooth-icon {
  max-width: 200px;
  height: auto;
  filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.1));
}

@keyframes float {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-20px);
  }
}

.display-1 {
  font-size: 8rem;
  line-height: 1;
  text-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

@media (max-width: 768px) {
  .display-1 {
    font-size: 5rem;
  }

  .tooth-icon {
    max-width: 150px;
  }

  .logo-404 {
    height: 3rem;
  }
}

.btn {
  border-radius: 50px;
  font-weight: 600;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.btn-accent {
  background-color: var(--bs-accent);
  border-color: var(--bs-accent);
  color: white;
}

.btn-accent:hover {
  background-color: var(--bs-accent);
  border-color: var(--bs-accent);
  filter: brightness(95%);
}
</style>
