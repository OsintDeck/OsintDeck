#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
=========================================================
OSINT Deck — Publicar solo en GitHub (sin SSH)
=========================================================

Ver: python release_github.py --help

Ejecutarlo sin argumentos abre el modo guiado (explica y pregunta).
=========================================================
"""

from __future__ import annotations

import importlib.util
import os
import re
import sys
from typing import TextIO

PLUGIN_ROOT = os.path.dirname(os.path.abspath(__file__))
DEPLOY_PY = os.path.join(PLUGIN_ROOT, "deploy_python.py")

if not os.path.isfile(DEPLOY_PY):
    print(
        "Error: hace falta deploy_python.py junto a release_github.py.\n"
        f"Esperado en: {DEPLOY_PY}",
        file=sys.stderr,
    )
    sys.exit(1)

_spec = importlib.util.spec_from_file_location("deploy_python", DEPLOY_PY)
if _spec is None or _spec.loader is None:
    print("Error: no se pudo cargar deploy_python.", file=sys.stderr)
    sys.exit(1)

d = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(d)

BOOTSTRAP_REL = os.path.join("src", "Core", "Bootstrap.php")

STEP_TOTAL = 7


# ---------------------------------------------------------------------------
# Salida / interacción
# ---------------------------------------------------------------------------
def is_interactive() -> bool:
    return sys.stdin.isatty() and sys.stdout.isatty()


def say(msg: str = "", file: TextIO | None = None) -> None:
    print(msg, file=file if file is not None else sys.stdout)


def hr() -> None:
    say("-" * 64)


def print_banner() -> None:
    say()
    say("╔" + "═" * 62 + "╗")
    say("║  OSINT Deck — release_github.py                              ║")
    say("║  Subir al repo GitHub + crear release (ZIP) — sin SSH        ║")
    say("╚" + "═" * 62 + "╝")
    say()


def print_what_this_does() -> None:
    say("¿Qué hace este script, en orden?")
    say()
    say("  1) Escribe la versión en tu copia del plugin (osint-deck.php,")
    say("     constante OSINT_DECK_VERSION y src/Core/Bootstrap.php) y")
    say("     ejecuta composer install --no-dev (salvo --skip-composer).")
    say("  2) Copia TODO el plugin desde esta carpeta hacia el clon de GitHub")
    say("     (la ruta que indiques con --repo-dir), excluyendo lo que define")
    say("     deploy_python.py (por ej. deploy_python.py, tokens, .venv…).")
    say("  3) Genera un ZIP con estructura wp-content/plugins/osint-deck/…")
    say("     (como lo espera WordPress y el Plugin Update Checker).")
    say("  4) Hace git commit + push en la rama actual del clon.")
    say("  5) Crea o mueve el tag vX.Y.Z y lo sube (push --force tag).")
    say("  6) Crea o actualiza la release en GitHub (prerelease BETA) y")
    say("     adjunta el ZIP como asset.")
    say("  7) Guarda últimos datos en .deploy_state.json (local).")
    say()
    say("NO hace: deploy por SSH al servidor (eso es deploy_python.py / both).")
    say()


def print_requirements() -> None:
    say("Requisitos previos:")
    say()
    say("  • Git instalado y en el PATH.")
    say("  • Un clon del repo OsintDeck/OsintDeck con remote origin correcto.")
    say("  • GITHUB_TOKEN: API de releases + push por HTTPS (mismo token con permiso repo).")
    say("    PAT classic (ghp_): listo. Fine-grained: además $env:GITHUB_LOGIN=\"tu_usuario\"")
    say("    Renovar o crear token: https://github.com/settings/tokens")
    say("    (Si vence o falla la auth, el script imprime los pasos detallados.)")
    say('    PowerShell:  $env:GITHUB_TOKEN="ghp_xxxx"')
    say("  • Opcional: archivo .env en esta carpeta con GITHUB_TOKEN=…")
    say("    (deploy_python.py lo lee automáticamente).")
    say("  • deploy_python.py en la misma carpeta que este script.")
    say("  • Composer: antes del ZIP corre composer install --no-dev.")
    say("    En PATH (composer / composer.bat) o variable COMPOSER_PATH (ruta al .bat/.exe/.phar).")
    say("    Si usás .phar: PHP en PATH o PHP_EXE. Para omitir Composer: --skip-composer")
    say()


def print_help_long() -> None:
    print_banner()
    print_what_this_does()
    hr()
    print_requirements()
    hr()
    say("Cómo usarlo (referencia rápida):")
    say()
    say("  python release_github.py                      → modo guiado si hay consola")
    say("  python release_github.py --help               → esta ayuda")
    say("  python release_github.py --repo-dir RUTA      → clon de GitHub (obligatorio la 1.ª vez)")
    say("  python release_github.py --bump patch         → sube 0.0.1 en la versión")
    say("  python release_github.py 0.7.0 --reason \"…\" ")
    say("  python release_github.py -y --repo-dir RUTA   → sin preguntar antes del push/release")
    say("  python release_github.py ... --skip-composer   → no ejecuta composer (ZIP puede fallar en WP)")
    say()
    say("Opciones útiles (iguales que deploy_python.py github):")
    say("  --source-dir   carpeta del plugin en desarrollo (defecto: carpeta de este script)")
    say("  --bump patch|minor|major")
    say("  --reason \"texto\"   mensaje del commit (si no, pregunta o popup)")
    say("  --type feat --scope admin   conventional commits")
    say("  --notes-file release.md     notas extra en el cuerpo de la release")
    say("  --rebase-reason \"…\"       si el push es rechazado y hay que rebase")
    say()
    say("La ruta de --repo-dir se guarda en .deploy_state.json para la próxima vez.")
    say()
    say("Recordatorio: el tag y la versión del header del plugin deben coincidir")
    say("para que WordPress (Plugin Update Checker) detecte actualizaciones.")
    say()


def parse_own_flags(argv: list[str]) -> tuple[list[str], bool, bool]:
    """
    Devuelve (argv_sin_flags, skip_confirm, guided).

    Modo guiado: argv vacío después de quitar -y/--yes (ej. solo
    ``python release_github.py``). Si pasás --repo-dir, versión u otras
    opciones, no pregunta lo básico por consola.
    """
    skip_confirm = False
    new_argv: list[str] = []
    for a in argv:
        if a in ("--yes", "-y"):
            skip_confirm = True
            continue
        new_argv.append(a)

    guided = len(new_argv) == 0 and not skip_confirm
    return new_argv, skip_confirm, guided


def prompt_line(label: str, default: str | None = None) -> str:
    suf = f" [{default}]" if default else ""
    raw = input(f"{label}{suf}\n> ").strip()
    if not raw and default:
        return default
    return raw


def confirm_or_exit(question: str, skip_if_yes_flag: bool) -> None:
    if skip_if_yes_flag:
        say("    (confirmación omitida por --yes)")
        return
    if not is_interactive():
        say("    (no hay TTY; se continúa sin confirmación interactiva)")
        return
    hr()
    ans = input(f"{question} [s/N]: ").strip().lower()
    if ans not in ("s", "si", "sí", "y", "yes"):
        raise RuntimeError("Cancelado por el usuario.")
    say()


def check_github_token_report() -> bool:
    tok = os.environ.get("GITHUB_TOKEN", "").strip()
    if tok:
        say(f"    GITHUB_TOKEN: OK (longitud {len(tok)}).")
        say("    Si caduca o lo revocás, al fallar el push o la API verás cómo generar uno nuevo.")
        return True
    say("    GITHUB_TOKEN: NO definido — sin esto fallará la API de GitHub.")
    say()
    for line in d.hint_github_token_renewal_es().split("\n"):
        say(f"    {line}")
    return False


def step_banner(step: int, title: str, explanation: str) -> None:
    say()
    hr()
    say(f">>> Paso {step}/{STEP_TOTAL}: {title}")
    say()
    for line in explanation.strip().split("\n"):
        say(f"    {line}")
    say()


def bump_bootstrap_version(work_dir: str, version: str) -> None:
    path = os.path.join(work_dir, BOOTSTRAP_REL)
    if not os.path.isfile(path):
        say(f"    (omitido) No está {BOOTSTRAP_REL} bajo {work_dir}")
        return
    content = d.read_text(path)
    content2, n = re.subn(
        rf"(?im)^(\s*const\s+VERSION\s*=\s*['\"]){d.SEMVER_RE}(['\"]\s*;\s*)$",
        rf"\g<1>{version}\g<2>",
        content,
        count=1,
    )
    d.write_text(path, content2)
    say(f"    Actualizado {BOOTSTRAP_REL}: const VERSION={'OK' if n else 'NO ENCONTRADO'}")


def apply_version(work_dir: str, version: str) -> None:
    d.bump_version(version, work_dir)
    bump_bootstrap_version(work_dir, version)


def interactive_guided_setup(
    state: dict,
    argv: list[str],
) -> tuple[list[str], bool]:
    """
    Si no pasaron argumentos: explicar y rellenar repo-dir / bump por preguntas.
    Devuelve (argv_enriquecido, skip_confirm).
    """
    if not is_interactive():
        say("Sin argumentos y sin consola interactiva: usá --repo-dir o --help.")
        say("Ejemplo: python release_github.py --repo-dir C:\\repos\\OsintDeck")
        sys.exit(2)

    say("── Modo guiado ──")
    say("Te pido lo mínimo. Ayuda larga: python release_github.py --help")
    say("Cancelar en cualquier momento: Ctrl+C")
    say()

    repo = state.get("repo_dir") or ""
    say()
    r = prompt_line("Ruta absoluta del clon Git (OsintDeck)", default=repo or None)
    if not r:
        raise RuntimeError("Hace falta la ruta del repositorio clonado.")
    argv = list(argv) + ["--repo-dir", os.path.expandvars(os.path.expanduser(r))]

    say()
    say("Versión a publicar:")
    say("  [Enter] = dejar la que ya está en osint-deck.php sin cambiar número")
    say("  p       = bump patch (0.6.18 → 0.6.19)")
    say("  n       = bump minor (0.6.18 → 0.7.0)")
    say("  j       = bump major (0.6.18 → 1.0.0)")
    say("  o       = escribir a mano ej. 0.7.2")
    say()
    choice = input("Tu elección [Enter/p/n/j/o]: ").strip().lower()
    if choice in ("p", "patch"):
        argv.extend(["--bump", "patch"])
    elif choice in ("n", "minor"):
        argv.extend(["--bump", "minor"])
    elif choice in ("j", "major"):
        argv.extend(["--bump", "major"])
    elif choice in ("o", "manual"):
        v = input("Versión semver (ej. 0.7.2): ").strip()
        if not v:
            raise RuntimeError("Versión vacía.")
        argv.insert(0, v)
    elif choice == "":
        pass
    else:
        if re.fullmatch(d.SEMVER_RE, choice):
            argv.insert(0, choice)
        else:
            say("No entendí la opción; uso la versión del archivo sin bump.")
    say()

    if input("¿Saltar la pregunta final antes de subir a GitHub? (--yes) [s/N]: ").strip().lower() in (
        "s",
        "si",
        "sí",
        "y",
    ):
        skip_confirm = True
    else:
        skip_confirm = False

    return argv, skip_confirm


def usage_short() -> None:
    say("Uso breve: python release_github.py --help")
    say("O bien:    python release_github.py --repo-dir RUTA_CLON")
    say("Modo guiado: python release_github.py")


def main() -> None:
    argv = sys.argv[1:]

    if "--help" in argv or "-h" in argv:
        print_help_long()
        return

    argv, skip_confirm_flag, guided = parse_own_flags(argv)

    d.load_env_file()
    state = d.load_state()

    print_banner()
    print_what_this_does()
    hr()
    print_requirements()
    token_ok = check_github_token_report()
    if not token_ok and is_interactive():
        confirm_or_exit(
            "No hay GITHUB_TOKEN: la release fallará al final. ¿Seguir igual para revisar pasos previos?",
            skip_if_yes_flag=skip_confirm_flag,
        )

    if guided:
        argv, extra_skip = interactive_guided_setup(state, argv)
        skip_confirm_flag = skip_confirm_flag or extra_skip

    version: str | None = None
    if len(argv) >= 1 and not argv[0].startswith("--"):
        version = argv[0].strip()
        argv = argv[1:]

    repo_dir = d.parse_arg(argv, "--repo-dir") or state.get("repo_dir")
    source_dir = d.parse_arg(argv, "--source-dir") or state.get("source_dir") or PLUGIN_ROOT
    bump_part = d.parse_arg(argv, "--bump")
    reason_arg = d.parse_arg(argv, "--reason")
    rebase_reason_arg = d.parse_arg(argv, "--rebase-reason")
    type_arg = d.parse_arg(argv, "--type")
    scope_arg = d.parse_arg(argv, "--scope")
    notes_file = d.parse_arg(argv, "--notes-file")

    skip_composer = "--skip-composer" in argv
    argv = [a for a in argv if a != "--skip-composer"]

    if repo_dir:
        state["repo_dir"] = repo_dir
    if source_dir:
        state["source_dir"] = source_dir

    if not repo_dir:
        usage_short()
        raise RuntimeError(
            "Falta --repo-dir (ruta del clon). Ejemplo:\n"
            "  python release_github.py --repo-dir C:\\repos\\OsintDeck\n"
            "O ejecutá sin argumentos para el modo guiado."
        )

    if not d.is_git_repo(repo_dir):
        raise RuntimeError(f"--repo-dir no es un repositorio git: {repo_dir}")

    say()
    say("── Rutas ──")
    say(f"  Origen del plugin:  {source_dir}")
    say(f"  Clon Git (--repo-dir): {repo_dir}")
    say()

    d.ensure_plugin_dir(source_dir)

    if not version:
        v = d.get_current_version(source_dir)
        if not v:
            raise RuntimeError(f"No se pudo leer la versión desde {os.path.join(source_dir, d.MAIN_FILE)}")
        version = v
        say(f"    Versión actual en disco (sin bump explícito): {version}")

    if bump_part:
        version = d.bump_semver(version, bump_part)
        say(f"    Tras --bump {bump_part}: {version}")

    if not re.fullmatch(d.SEMVER_RE, version):
        raise RuntimeError("Versión inválida (semver esperado, ej. 0.7.0).")

    tag = f"v{version}"

    if reason_arg and reason_arg.strip():
        reason = reason_arg.strip()
    else:
        default_reason = (state.get("last_reason") or "").strip() or None
        reason = d.ask_reason(
            "Release GitHub",
            f"Motivo del commit y la release (v{version}):",
            default=default_reason,
        )

    rebase_reason = (rebase_reason_arg.strip() if rebase_reason_arg else None) or reason
    ctype, cscope = d.normalize_type_scope(type_arg, scope_arg)
    commit_message = d.build_conventional_commit_message(ctype, cscope, reason)

    state["last_reason"] = reason
    state["last_reason_utc"] = d.now_utc()
    state["last_commit_type"] = ctype
    state["last_commit_scope"] = cscope

    notes_md = d.read_optional_file(notes_file)

    say()
    say("── Resumen antes de tocar archivos ──")
    say(f"  Versión final:     {version}")
    say(f"  Tag:               {tag}")
    say(f"  Mensaje de commit: {commit_message}")
    say()

    confirm_or_exit(
        "¿Aplicar esta versión en el plugin, copiar al clon y subir a GitHub?",
        skip_if_yes_flag=skip_confirm_flag,
    )

    step_banner(
        1,
        "Versión en origen + Composer (vendor)",
        "Se actualizan osint-deck.php (header + OSINT_DECK_VERSION) y\n"
        "src/Core/Bootstrap.php (const VERSION).\n"
        "Luego: composer install --no-dev (salvo --skip-composer).",
    )
    apply_version(source_dir, version)
    d.composer_production_install(source_dir, skip=skip_composer)

    zip_path: str | None = None
    try:
        step_banner(
            2,
            "Sincronizar plugin → clon del repo",
            "Borra en el clon todo lo que no está preservado (.git, LICENSE, etc.)\n"
            "y copia desde la carpeta de desarrollo. deploy_python.py no se sube.",
        )
        d.sync_plugin_to_repo(source_dir, repo_dir)

        step_banner(
            3,
            "Re-aplicar versión en el clon",
            "Por si algún archivo no quedó alineado tras la copia.",
        )
        apply_version(repo_dir, version)

        step_banner(
            4,
            "Generar ZIP",
            "ZIP temporal con prefijo osint-deck/ en cada ruta (instalable en WordPress).",
        )
        zip_path = d.create_zip(version, repo_dir)

        step_banner(
            5,
            "Changelog para la release",
            "Mensajes de git desde el último tag hasta HEAD (agrupados por tipo).",
        )
        last_tag = d.git_get_last_tag(repo_dir)
        changelog_md = d.git_build_changelog_grouped(repo_dir, last_tag)
        if last_tag:
            say(f"    Último tag visto en el clon: {last_tag}")
        else:
            say("    No había tag previo; el changelog usa todo el historial relevante.")

        step_banner(
            6,
            "Git: commit, push y tag",
            "git add -A, commit, push, tag -f vX.Y.Z, push -f del tag.\n"
            "Si el remoto rechaza el push, puede pedir rebase (como deploy_python).",
        )
        confirm_or_exit(
            "Estás por hacer PUSH y TAG al remoto. ¿Continuar?",
            skip_if_yes_flag=skip_confirm_flag,
        )
        d.git_commit_tag_push(repo_dir, tag, commit_message, rebase_reason=rebase_reason)

        step_banner(
            7,
            "Release en GitHub",
            "API de GitHub: prerelease BETA, notas, y subida del ZIP como asset.",
        )
        confirm_or_exit(
            "¿Crear/actualizar la release y subir el ZIP?",
            skip_if_yes_flag=skip_confirm_flag,
        )

        release_body = d.build_release_body(
            version=version,
            reason=reason,
            changelog_md=changelog_md,
            notes_md=notes_md,
        )
        release_status = d.github_release_beta_upsert(version, tag, zip_path, release_body)
        d.stamp(state, "github", version)
        d.save_state(state)

        say()
        hr()
        say("LISTO — solo GitHub (sin SSH al servidor)")
        hr()
        say(f"  Versión publicada: {version}")
        say(f"  Commit:            {commit_message}")
        if last_tag:
            say(f"  Changelog desde:   {last_tag}")
        say(f"  Tag en remoto:     {tag}")
        say(f"  Release API:       {release_status}")
        say(f"  Estado local:      {d.STATE_FILE}")
        say()
        say("Próxima vez podés repetir:")
        say(f'  python release_github.py --repo-dir "{repo_dir}"')
        say("  (o sin --repo-dir si ya quedó guardado en .deploy_state.json)")
        say()

    finally:
        d.cleanup(zip_path)


if __name__ == "__main__":
    try:
        main()
    except RuntimeError as ex:
        say(f"\nCancelado o error: {ex}", file=sys.stderr)
        if "── Cómo renovar GITHUB_TOKEN ──" not in str(ex) and d.github_auth_failure_heuristic(
            str(ex)
        ):
            say("\n" + d.hint_github_token_renewal_es(), file=sys.stderr)
        sys.exit(1)
    except Exception as ex:
        em = str(ex)
        say(f"\nFallo: {ex}", file=sys.stderr)
        if "── Cómo renovar GITHUB_TOKEN ──" not in em and d.github_auth_failure_heuristic(em):
            say("\n" + d.hint_github_token_renewal_es(), file=sys.stderr)
        sys.exit(1)
