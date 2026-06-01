# Sistema de Control de Transmetro 🚌💨
### Municipalidad de Guatemala | Dirección de Movilidad Urbana

Este repositorio contiene el desarrollo del **Sistema de Control de Transmetro**, una propuesta tecnológica integral diseñada para optimizar la gestión de flotas, coordinar la logística de rutas y robustecer la seguridad ciudadana en las estaciones del municipio.

Desarrollado como proyecto central para el curso de **Análisis de Sistemas** de la Facultad de Ingeniería en Sistemas.

---

## 👤 Información del Autor
* **Nombre:** Eduardo Josue García Díaz
* **Carnet:** 090-21-2641
* **Facultad:** Ingeniería en Sistemas
* **Curso:** Análisis de Sistemas
* **Presentado a:** Municipalidad de Guatemala

---

## 📋 Estructura de Entregables del Proyecto
El repositorio está organizado siguiendo el cronograma oficial y el ciclo de vida del desarrollo de sistemas (SDLC):

1. **Carta de Presentación** 📄: Delimitación de objetivos y alcances con la municipalidad.
2. **Planificación** 📅: Cronograma de hitos clave de marzo a mayo de 2026.
3. **Estudio de Factibilidad** 📊: Evaluación multidimensional (Técnica, Operativa, Económica y Legal).
4. **Análisis Metodológico** 🔍: Diagnóstico de la situación actual y mitigación del "Punto Ciego" de conectividad (Solución al Ítem 23).
5. **Análisis de Requerimientos** 📝: Matriz de 10 requerimientos funcionales y no funcionales core.
6. **Propuesta Económica** 💰: Desglose de inversión (CAPEX/OPEX) y Contrato de Aceptación.
7. **Diagramas UML** 🛠️: Modelado de 10 Casos de Uso del sistema.
8. **Diagrama Entidad-Relación (DER)** 🗄️: Diseño lógico y arquitectura de la base de datos relacional.
9. **Prototipo Funcional** 💻: Implementación física del sistema.

---

## 🚀 Características Clave del Prototipo
* **Gestión de Flotas Inteligente:** Control automático de unidades en ruta basado en la densidad de pasajeros, respetando los límites de operación de la línea ($N$ a $2N$).
* **Seguridad Ciudadana:** Módulo de asignación de guardias por acceso físico en estaciones y administración de Expedientes de Confianza para pilotos.
* **Resiliencia ante Fallas de Red (Solución Ítem 23):** Arquitectura híbrida con capacidad de operación local autónoma (*Local First*) y sincronización diferida en fondo una vez reestablecido el canal VPN central.
* **Optimización Energética:** Algoritmo de retraso controlado (5 minutos) en andén si la carga de la unidad es inferior al 25% para evitar el desperdicio de combustible.

---

## 🛠️ Tecnologías Utilizadas
* **Backend & Lógica de Negocio:** PHP 8.x
* **Base de Datos:** MySQL / MariaDB (Esquema relacional con integridad referencial estricta)
* **Frontend / UI Gerencial:** HTML5, CSS3 (Diseño responsivo, modo oscuro para mitigar fatiga visual)
