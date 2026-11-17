.PHONY: help ami init fmt validate plan apply destroy

help:
	@printf '%s\n' \
		'ami       Build the Packer AMI' \
		'init      Initialise Terraform' \
		'fmt       Format Terraform files' \
		'validate  Validate Terraform configuration' \
		'plan      Plan the Terraform changes' \
		'apply     Apply the Terraform changes' \
		'destroy   Destroy the stack (requires confirmation)'

ami:
	./scripts/build-ami.sh

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
