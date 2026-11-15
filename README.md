# VanBlue — Aegis: Autonomous Crew Health Monitor for Deep Space

> **NASA Space Apps Challenge 2026** · An AI-assisted flight-surgeon dashboard that keeps a deep-space crew healthy when Earth is minutes of light-delay away.

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4) ![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20) ![Tailwind](https://img.shields.io/badge/Tailwind_CSS-4-38BDF8) ![ECharts](https://img.shields.io/badge/ECharts-5.5-AA344D) ![License](https://img.shields.io/badge/license-MIT-green)

---

## Table of Contents

- [The Problem](#the-problem)
- [Our Solution](#our-solution)
- [Features](#features)
- [How It Works](#how-it-works)
- [NASA Data](#nasa-data)
- [Other Data & Libraries](#space-agency-partner--other-data)
- [Use of Artificial Intelligence](#use-of-artificial-intelligence)
- [Tech Stack](#tech-stack)
- [Getting Started](#getting-started)
- [API Reference](#api-reference)
- [Testing](#testing)
- [Project Structure](#project-structure)
- [Disclaimer](#disclaimer)

---

## The Problem

On a Mars transit, a crew can be **up to ~22 minutes of one-way light-delay** away from Mission Control. Real-time medical guidance from Earth stops being possible. At the same time, the crew is exposed to risks that do not exist on Earth:

- Galactic cosmic rays and solar particle events (SPE)
- Bone and muscle loss (~1–1.5 % bone mass per month)
- Spaceflight-Associated Neuro-ocular Syndrome (SANS)
- Immune dysregulation, sleep debt and cognitive decline
- Cabin CO₂ build-up and life-support faults

The crew needs an **onboard medical officer** that can watch every astronaut continuously, explain risks, and act autonomously when Earth cannot answer in time.

## Our Solution

**VanBlue Aegis** is a mission-control style web dashboard for a simulated four-person deep-space crew (**Astronaut 1–4**, fictional) on the *VanBlue Ares-I* mission. It combines:

1. **Live simulated telemetry** for each astronaut (heart rate, SpO₂, blood pressure, temperature, respiration, hydration, radiation dose).
2. **NASA reference data** — NASA-STD-3001 thresholds, MSL RAD deep-space dosimetry, OSDR spaceflight biology studies and HRP risk evidence — used to judge that telemetry.
3. **Live space weather** from NASA DONKI (solar flares and CMEs).
4. **An AI flight surgeon ("Aegis")** that triages symptoms, recommends onboard medications and countermeasures, and speaks to the crew by voice.

## Features

| Panel | What it does |
|---|---|
| **Mission Control** | Mission day, Earth distance, communication delay, crew status at a glance |
| **Crew Health** | Per-astronaut live vitals, body hologram and composite health radar |
| **Radiation & Space Weather** | Cumulative dose vs. NASA-STD-3001 career limits, live DONKI flare/CME feed, SPE simulation |
| **Autonomous AI Triage** | Rule-based triage against clinical thresholds, with automatic event logging |
| **Health Detective** | Investigates unexpected health changes (cabin CO₂, sensor fault, individual illness) and separates environmental from personal causes |
| **AI Flight Surgeon** | Voice/text consultation with photo attachment, red-flag detection and structured recommendations |
| **Space Conditions** | Projection of bone loss, muscle atrophy, SANS, etc. by mission day and countermeasure compliance |
| **Countermeasures & Cognitive** | ARED / CEVIS / LBNP exercise schedule and PVT cognitive test results |
| **Ship Pharmacy** | Onboard medication inventory, dispensing and low-stock alerts |
| **NASA Data Center** | Explorer for the NASA datasets used in the project |
| **Medical Report** | Printable per-astronaut medical summary |
| **Project & Impact** | The story, impact and target audience of the project |

Extra: **AI daily briefing** (spoken), **guided jury demo tour**, and keyboard shortcuts (`1`–`12`) for panels.

## How It Works

```
 NASA datasets (local mirror)      NASA DONKI / OSDR APIs (live, with fallback)
            │                                     │
            ▼                                     ▼
   NasaDataRepository ──────────► SpaceWeatherService / OsdrService
            │
            ▼
   CrewHealthEngine  ── simulated telemetry + threshold triage
   SpaceMedicineEngine ── condition projection by mission day
   MissionDetective ── root-cause investigation
            │
            ▼
   AiFlightSurgeon / CrewBriefingService ──► Gemini API (falls back to rule-based answers)
            │
            ▼
   Laravel JSON API  ──►  Blade + Tailwind + ECharts dashboard (voice via Web Speech API)
```

- Live APIs are always optional: if NASA DONKI, OSDR or the AI service is unreachable, the app falls back to verified local snapshots and rule-based logic, so the demo never breaks.
- Every triage, consultation, medication and countermeasure is stored as a `HealthEvent` to build each astronaut's medical history.

## NASA Data

| Dataset | Source | How we used it |
|---|---|---|
| **NASA-STD-3001 Vol. 1 Rev B (2022)** — Space Flight Human-System Standard | [nasa.gov/ochmo/human-system-standards](https://www.nasa.gov/ochmo/human-system-standards/) | Radiation exposure limits used for cumulative-dose alerts and the radiation panel. Mirrored in `database/nasa_data/nasa_hrp_clinical_thresholds.json`. |
| **MSL Radiation Assessment Detector (RAD)** — deep-space cruise dosimetry (Zeitlin et al., *Science* 2013, [doi:10.1126/science.1235988](https://doi.org/10.1126/science.1235988)) | NASA Planetary Data System — [pds-ppi.igpp.ucla.edu](https://pds-ppi.igpp.ucla.edu/) | GCR daily dose rate (~1.84 mSv/day) and SPE dose values drive each astronaut's daily dose and the SPE simulation. Mirrored in `nasa_msl_radiation_dosimetry.json`. |
| **NASA DONKI** — Space Weather Database (FLR, CME endpoints) | [kauai.ccmc.gsfc.nasa.gov/DONKI](https://kauai.ccmc.gsfc.nasa.gov/DONKI/) / [api.nasa.gov](https://api.nasa.gov/) | Live solar flares and CMEs from recent days, used for the space-weather alert level. |
| **NASA Open Science Data Repository (OSDR)** — OSD-87 (STS-135 mouse retina), OSD-242 (ISS mouse liver), OSD-137 (Rodent Research-3) | [osdr.nasa.gov](https://osdr.nasa.gov/bio/repo/) · [Biological Data API](https://visualization.osdr.nasa.gov/biodata/api/v2) | Study metadata fetched from the OSDR API (snapshot fallback in `nasa_osdr_studies.json`) and linked as evidence to the SANS and immune conditions. |
| **NASA Human Research Program (HRP)** — Risk & Evidence Books | [humanresearchroadmap.nasa.gov](https://humanresearchroadmap.nasa.gov/) | Rates and descriptions for bone loss, muscle atrophy, SANS, immune and other spaceflight conditions in `space_medical_conditions.json`. |

## Space Agency Partner & Other Data

| Resource | Use | License |
|---|---|---|
| [Astronaut database (Stavnichuk & Corlett, 2020), Mendeley Data, doi:10.17632/86tsnnbv2w.1](https://data.mendeley.com/datasets/86tsnnbv2w/1) — `nasa_astronauts_registry.csv`, 1,277 missions | Aggregate statistics in the NASA Data Center panel (flight hours, EVA hours, nationality) | CC BY 4.0 |
| General clinical reference ranges (heart rate, SpO₂, temperature) | Triage thresholds; marked as **not** NASA standards in the data file | Public medical references |
| [Laravel](https://laravel.com) | Backend framework | MIT |
| [Tailwind CSS](https://tailwindcss.com) | Styling | MIT |
| [Apache ECharts](https://echarts.apache.org) | Charts and radar plots | Apache 2.0 |
| [Google Fonts](https://fonts.google.com) — Plus Jakarta Sans, Space Grotesk, Orbitron, JetBrains Mono | Typography | SIL OFL 1.1 |
| [Google Gemini API](https://ai.google.dev) | AI flight surgeon and briefings | Google API Terms |

**Tools used for the demo video and slides**

| Resource | Use | License / Terms |
|---|---|---|
| [Higgsfield](https://higgsfield.ai) — Nano Banana Pro image model | AI-generated space background images in the demo video and slides (watermarked "AI-generated") | Higgsfield Terms of Service |
| [ElevenLabs](https://elevenlabs.io) text-to-speech (via Higgsfield) | AI-generated Turkish voiceover in the demo video | ElevenLabs / Higgsfield Terms of Service |
| [FFmpeg](https://ffmpeg.org) | Video encoding and audio processing | LGPL / GPL |
| [Pillow](https://python-pillow.org) and [NumPy](https://numpy.org) | Frame rendering, motion graphics and procedurally generated background music | MIT-CMU / BSD |
| [PptxGenJS](https://gitbrent.github.io/PptxGenJS/) | Building the PowerPoint slide deck | MIT |
| [Google Chrome](https://www.google.com/chrome/) (headless) | Capturing real screenshots of the app for the video and slides | Google Terms of Service |

The crew (**Astronaut 1–4**) and the *VanBlue Ares-I* mission are **fictional**. No real person's personal data is used. No NASA logos, flags or mission insignia are used in the app.

## Use of Artificial Intelligence

We used AI in two ways and disclose both:

**1. AI inside the product**
- The **Aegis AI flight surgeon** and the **daily crew briefing** call the Google Gemini API (`app/Services/GeminiClient.php`, `AiFlightSurgeon.php`, `CrewBriefingService.php`). Answers are labelled as AI decision support, not diagnosis. When the API is unavailable, deterministic rule-based logic is used instead.

**2. AI used during development**
- **Code:** AI coding assistants (Claude, Anthropic) helped write and refactor parts of the backend services, front-end JavaScript and tests. All AI-assisted code was reviewed and tested by the team.
- **Images:** The astronaut hologram image (`public/images/astronaut.png`) is AI-generated and carries a visible **"AI-generated"** watermark. All agency logos and mission insignia were removed from it.
- **Text:** AI helped draft and translate parts of the documentation.

**3. AI used in the demo video and slides**
- **Images:** The space background images (spacecraft, medical bay, hologram scene) were generated with Higgsfield (Nano Banana Pro). Each one carries a visible **"AI-generated"** watermark in the video and the slides. No NASA logos, flags or mission insignia were used or recreated.
- **Audio:** The Turkish voiceover was generated with ElevenLabs text-to-speech (via Higgsfield). The video shows an on-screen "AI voice" note, and the MP4 file metadata (`comment` field) states it as well.
- **Code:** Claude (Anthropic) helped write the scripts that render the video, the motion graphics, the procedural background music and the slide deck.
- **Not AI-generated:** The product scenes are real screen captures of the running VanBlue Aegis app. The background music was generated procedurally by our own script, not by an AI music model.

All data selection, medical logic design, threshold choices and design decisions were made by the team.

## Tech Stack

- **Backend:** PHP 8.5, Laravel 13, SQLite
- **Frontend:** Blade, Tailwind CSS 4, vanilla JavaScript, Apache ECharts 5.5
- **Voice:** Web Speech API (speech synthesis, speech recognition)
- **AI:** Google Gemini API
- **Testing / tooling:** PHPUnit, Laravel Pint, Vite

## Getting Started

### Requirements

- PHP 8.5+, Composer, Node.js 20+
- (Optional) A Gemini API key and a NASA API key from [api.nasa.gov](https://api.nasa.gov/)

### Installation

```bash
git clone <repository-url> vanblue
cd vanblue

composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate:fresh --seed
npm run build

php artisan serve
```

Then open `http://127.0.0.1:8000`.

### Environment Variables

| Variable | Description |
|---|---|
| `GEMINI_API_KEY` | Google Gemini key for the AI flight surgeon (optional; rule-based fallback without it) |
| `GEMINI_BASE_URL` | Base URL of the Gemini-compatible endpoint |
| `GEMINI_MODEL` | Gemini model name |
| `NASA_API_KEY` | api.nasa.gov key for DONKI (optional; defaults to `DEMO_KEY`, with local fallback) |
| `NASA_DONKI_URL`, `NASA_OSDR_URL` | Override the NASA API base URLs (optional) |

See `.env.example` for the full list.

## API Reference

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/telemetry/live?member=astronot1` | Live vitals and triage for one astronaut |
| GET | `/api/crew` | Whole-crew summary |
| GET | `/api/mission` | Mission status |
| POST | `/api/emergency` | Toggle solar particle event simulation |
| GET | `/api/conditions?member=&day=&compliance=` | Spaceflight condition projections |
| GET | `/api/space-weather` | NASA DONKI flare/CME summary |
| GET | `/api/nasa/datasets` | NASA dataset catalogue |
| GET | `/api/nasa/astronauts/stats` | Astronaut registry statistics |
| POST | `/api/assistant/consult` | AI flight-surgeon consultation |
| GET | `/api/assistant/briefing` | AI daily crew briefing |
| GET | `/api/medications` | Ship pharmacy inventory |
| POST | `/api/medications/{id}/dispense` | Dispense a medication |
| GET/POST | `/api/detective/*` | Health Detective scenarios and checks |
| GET/POST | `/api/events` | Crew health event log |

Crew member IDs: `astronot1`, `astronot2`, `astronot3`, `astronot4`.

## Testing

```bash
php artisan test --compact
```

## Project Structure

```
app/
├── Http/Controllers/     # Telemetry, Mission, Assistant, Medication, Detective, HealthEvent
├── Models/               # HealthEvent, Medication
└── Services/             # CrewHealthEngine, SpaceMedicineEngine, AiFlightSurgeon,
                          # MissionDetective, SpaceWeatherService, OsdrService, NasaDataRepository
database/nasa_data/       # Local mirrors of NASA datasets + fictional crew manifest
resources/views/panels/   # Dashboard panels (Blade)
public/js/vanblue.js      # Dashboard logic, charts, voice
tests/Feature/            # API feature tests
```

## Disclaimer

VanBlue Aegis is a **hackathon prototype** for decision support in a simulated mission. It is not a medical device and must not be used for real diagnosis or treatment. NASA does not endorse this project.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).
