# Contributing Guide

Welcome to the Horizion Interstellar System!  
This repository houses the **backend (Laravel 12 API)** and **frontend (Laravel 12)** for the Raven Guard organizational platform.

This document explains how to contribute safely and consistently.

---

## 🧭 Project Structure

/backend → Laravel 12 API (auth, Sanctum, ranks, UEX, missions)
/frontend → Laravel 12 (UI, auth relay, dashboards)


Backend and frontend live together in a monorepo, but each is treated as its own app.

---

## 🏗 Development Workflow

### 1. Create a branch
All work must happen in a feature branch.

git checkout -b feature/<ticket-or-task-name>



Examples:

- `feature/add-pagination`
- `feature/mission-endpoints`
- `fix/profile-endpoint`

Never push directly to `main`.

---

## 🔍 Code Review & Pull Requests

All PRs must:

1. Describe the change clearly  
2. Include screenshots or Postman examples if applicable  
3. Use proper formatting and naming  
4. Pass basic sanity (no debug dumps, no commented-out code blobs)

Icha (project lead) approves merges into `main`.

---

## 🧪 Testing Requirements

At minimum:

- All endpoints should be tested in Postman  
- No sensitive logic should be unauthenticated  
- Ranks, permissions, and token behavior must be validated

---

## 🧹 Clean Code Expectations

- Use consistent indentation (4 spaces PHP, 2 spaces JS)
- Follow Laravel conventions
- No “clever hacks” without explaining them
- Keep PRs reasonably small (one logical change per PR)
- No pushing experimental code directly to main

---

## 🎯 What You Can Work On

Helpers may safely take tasks such as:

- Pagination
- CRUD endpoints
- Small controllers
- Request validation
- API response formatting
- Basic migrations
- Documentation updates

Avoid deep architectural changes unless explicitly approved.

---

## 🛡 Project Philosophy

This system is long-term infrastructure, designed to be:

- Modular  
- Predictable  
- Secure  
- Easy to build on  

All contributions should preserve or strengthen these principles.

---

Thank you for supporting the project and helping bring Horizion to life.