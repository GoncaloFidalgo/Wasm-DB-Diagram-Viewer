# Wasm DB Diagram Viewer

Este projeto integra um backend desenvolvido em **Laravel** (com interface gerida em Filament) e um módulo gráfico para edição de diagramas desenvolvido em **Rust** e compilado para **WebAssembly (Wasm)**.

> **Nota:** As pastas `vendor` (PHP), `node_modules` (NPM) e `target` (Rust) foram removidas. Siga os passos abaixo para as reconstruir

---

## Pré-requisitos

Para executar este projeto localmente, o ambiente deve ter as seguintes ferramentas instaladas:

* **PHP** (v8.1 ou superior)
* **Composer** (Gestor de dependências do PHP)
* **Node.js e NPM** (Para compilação de assets do frontend)
* **Rust e Cargo** (Para compilar o motor do visualizador)
* **wasm-pack** (Para gerar o pacote WebAssembly. Instalável via `cargo install wasm-pack`)

---

## Configuração do Backend (Laravel)

Na raiz do projeto (pasta `LaravelFullStack`), execute os seguintes comandos para restaurar o ecossistema:

**Instalar as dependências do PHP:**
   ```
   composer install
```

## Compilação do WebAssembly (Opcional)

Os binários WebAssembly finais já se encontram disponíveis na pasta 
public/wasm do Laravel para facilitar a execução. 
Contudo, se desejar alterar o código fonte e recompilar o módulo Rust, 
siga estes passos:

**Navegar para o módulo Rust (pasta `RustWasmDiagramViewer`) e compilar e exportar os binários:**
   ```
 wasm-pack build --target web --out-dir ../LaravelFullStack/public/wasm
```

## Executar a aplicação
Com todas as dependências instaladas, inicie o servidor: 

``php artisan serve``