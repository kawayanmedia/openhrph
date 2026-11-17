.PHONY: help image init fmt validate plan apply destroy

help:
	@printf '%s\n' \
		'image     Build the Packer image (requires GCP_PROJECT_ID)' \
		'init      Initialise Terraform' \
		'fmt       Format Terraform files' \
		'validate  Validate Terraform configuration' \
		'plan      Plan the Terraform changes' \
		'apply     Apply the Terraform changes' \
		'destroy   Destroy the stack (requires confirmation)'

image:
	./scripts/build-image.sh

init:
	cd terraform && terraform init

fmt:
	cd terraform && terraform fmt -recursive

validate:
	cd terraform && terraform init -backend=false && terraform validate

plan:
	cd terraform && terraform plan

apply:
	./scripts/deploy.sh

destroy:
	./scripts/destroy.sh
